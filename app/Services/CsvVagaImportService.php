<?php

namespace App\Services;

use App\Models\Conhecimento;
use App\Models\MunicipioEs;
use App\Models\User;
use App\Models\VagaEmprego;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CsvVagaImportService
{
    /**
     * Required canonical columns for job vacancy CSV import.
     */
    public const REQUIRED_COLUMNS = [
        'nome_vaga' => 'Nome vaga',
        'descricao_vaga' => 'Descrição vaga',
        'quantidade_vaga' => 'Quantidade vaga',
        'data_inicio' => 'Data início',
        'data_fim' => 'Data fim',
        'conhecimento' => 'Conhecimento',
        'municipio' => 'Município / CEP',
    ];

    /**
     * Generate UTF-8 (with BOM) CSV template including system Conhecimentos reference examples.
     */
    public function generateTemplateCsv(): string
    {
        $catalogSkills = Conhecimento::query()
            ->where('ativo', true)
            ->orderBy('nome', 'asc')
            ->pluck('nome')
            ->values()
            ->all();

        $firstSkill = $catalogSkills[0] ?? 'Informática Básica e Pacote Office';
        $secondSkill = $catalogSkills[1] ?? $firstSkill;
        $thirdSkill = $catalogSkills[2] ?? $firstSkill;

        $stream = fopen('php://temp', 'r+');

        // UTF-8 BOM for Excel / LibreOffice compatibility
        fwrite($stream, "\xEF\xBB\xBF");

        // Header row matching exact specification
        fputcsv($stream, array_values(self::REQUIRED_COLUMNS), ';');

        // Sample row 1: Immediate start + Permanent end + Single system skill + Cidade Vitória
        fputcsv($stream, [
            'Auxiliar Operacional de Logística',
            'Atuação em conferência, separação de mercadorias e organização de pátio logístico.',
            '3',
            'imediato',
            'permanente',
            $firstSkill,
            'Vitória',
        ], ';');

        // Sample row 2: Specific start date + Specific end date + Multiple system skills + CEP Linhares
        $multiSkillExample = $secondSkill !== $thirdSkill
            ? "{$secondSkill} | {$thirdSkill}"
            : $secondSkill;

        fputcsv($stream, [
            'Oficial de Manutenção Predial',
            'Manutenção preventiva e corretiva em instalações prediais comerciais.',
            '2',
            now()->addDays(7)->format('d/m/Y'),
            now()->addMonths(6)->format('d/m/Y'),
            $multiSkillExample,
            '29900-000',
        ], ';');

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv !== false ? $csv : '';
    }

    /**
     * Parse, validate, and atomically persist job vacancies from CSV content.
     */
    public function importFromCsvContent(string $rawContent, User $user, ?int $municipioId = null): array
    {
        // Strip UTF-8 BOM if present
        $cleanContent = preg_replace('/^\xEF\xBB\xBF/', '', $rawContent) ?? $rawContent;

        // Convert legacy Excel Windows-1252 / ISO-8859-1 files to UTF-8 so accented headers & skills parse cleanly
        if ($cleanContent !== '' && !mb_check_encoding($cleanContent, 'UTF-8')) {
            $cleanContent = mb_convert_encoding($cleanContent, 'UTF-8', 'Windows-1252');
        }

        if (trim($cleanContent) === '') {
            return [
                'ok' => false,
                'http_status' => 422,
                'payload' => [
                    'status' => 'validation_error',
                    'error' => 'O arquivo CSV enviado está vazio.',
                    'message' => 'O arquivo CSV enviado está vazio.',
                    'total_registros' => 0,
                    'sucesso_count' => 0,
                    'erro_count' => 1,
                    'erros' => [
                        ['linha' => 1, 'campo' => 'arquivo', 'erro' => 'O arquivo CSV está vazio.'],
                    ],
                ],
            ];
        }

        $delimiter = $this->detectDelimiter($cleanContent);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $cleanContent);
        rewind($stream);

        $headerRow = null;
        $lineNumber = 0;

        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
            $lineNumber++;
            if ($this->isRowEmpty($row)) {
                continue;
            }
            $headerRow = $row;
            break;
        }

        if ($headerRow === null) {
            fclose($stream);
            return [
                'ok' => false,
                'http_status' => 422,
                'payload' => [
                    'status' => 'validation_error',
                    'error' => 'Cabeçalho do CSV não encontrado.',
                    'message' => 'Cabeçalho do CSV não encontrado.',
                    'total_registros' => 0,
                    'sucesso_count' => 0,
                    'erro_count' => 1,
                    'erros' => [
                        ['linha' => 1, 'campo' => 'cabecalho', 'erro' => 'Cabeçalho do CSV ausente ou inválido.'],
                    ],
                ],
            ];
        }

        $columnIndexes = $this->mapHeaderIndexes($headerRow);
        $missingColumns = [];
        foreach (self::REQUIRED_COLUMNS as $key => $label) {
            if (!array_key_exists($key, $columnIndexes)) {
                $missingColumns[] = $label;
            }
        }

        if (!empty($missingColumns)) {
            fclose($stream);
            $missingList = implode(', ', $missingColumns);
            return [
                'ok' => false,
                'http_status' => 422,
                'payload' => [
                    'status' => 'validation_error',
                    'error' => "Cabeçalho CSV inválido. Colunas obrigatórias ausentes: {$missingList}.",
                    'message' => "Cabeçalho CSV inválido. Colunas obrigatórias ausentes: {$missingList}.",
                    'total_registros' => 0,
                    'sucesso_count' => 0,
                    'erro_count' => 1,
                    'erros' => [
                        [
                            'linha' => $lineNumber,
                            'campo' => 'cabecalho',
                            'erro' => "Colunas obrigatórias ausentes: {$missingList}.",
                        ],
                    ],
                ],
            ];
        }

        // Load active catalog skills once for fast lookup
        $catalog = Conhecimento::query()
            ->where('ativo', true)
            ->get(['id', 'nome', 'categoria']);

        $catalogByNormalizedName = [];
        $catalogById = [];
        foreach ($catalog as $skill) {
            $catalogByNormalizedName[$this->normalizeKey($skill->nome)] = $skill;
            $catalogById[(int) $skill->id] = $skill;
        }

        $resolvedMunicipioId = $this->resolveMunicipioId($municipioId, $user);

        $validatedRows = [];
        $errors = [];
        $dataRowCount = 0;

        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
            $lineNumber++;
            if ($this->isRowEmpty($row)) {
                continue;
            }

            // Skip comment/instruction lines starting with #
            $firstCell = trim((string) ($row[0] ?? ''));
            if (str_starts_with($firstCell, '#')) {
                continue;
            }

            $dataRowCount++;
            $rowErrors = [];

            $nomeVaga = trim((string) ($row[$columnIndexes['nome_vaga']] ?? ''));
            $descricaoVaga = trim((string) ($row[$columnIndexes['descricao_vaga']] ?? ''));
            $quantidadeRaw = trim((string) ($row[$columnIndexes['quantidade_vaga']] ?? ''));
            $dataInicioRaw = trim((string) ($row[$columnIndexes['data_inicio']] ?? ''));
            $dataFimRaw = trim((string) ($row[$columnIndexes['data_fim']] ?? ''));
            $conhecimentoRaw = trim((string) ($row[$columnIndexes['conhecimento']] ?? ''));
            $municipioRaw = isset($columnIndexes['municipio']) ? trim((string) ($row[$columnIndexes['municipio']] ?? '')) : '';

            // 1. Nome vaga
            if ($nomeVaga === '') {
                $rowErrors[] = [
                    'linha' => $lineNumber,
                    'campo' => 'Nome vaga',
                    'erro' => "Linha {$lineNumber}: O campo 'Nome vaga' é obrigatório.",
                ];
            } elseif (mb_strlen($nomeVaga) > 150) {
                $rowErrors[] = [
                    'linha' => $lineNumber,
                    'campo' => 'Nome vaga',
                    'erro' => "Linha {$lineNumber}: 'Nome vaga' excede o limite de 150 caracteres.",
                ];
            }

            // 2. Descrição vaga
            if ($descricaoVaga === '') {
                $rowErrors[] = [
                    'linha' => $lineNumber,
                    'campo' => 'Descrição vaga',
                    'erro' => "Linha {$lineNumber}: O campo 'Descrição vaga' é obrigatório.",
                ];
            }

            // 3. Quantidade vaga
            $quantidade = null;
            if ($quantidadeRaw === '' || !preg_match('/^-?\d+$/', $quantidadeRaw)) {
                $rowErrors[] = [
                    'linha' => $lineNumber,
                    'campo' => 'Quantidade vaga',
                    'erro' => "Linha {$lineNumber}: 'Quantidade vaga' deve ser um número inteiro positivo (informado: '{$quantidadeRaw}').",
                ];
            } else {
                $quantidade = (int) $quantidadeRaw;
                if ($quantidade < 1 || $quantidade > 10000) {
                    $rowErrors[] = [
                        'linha' => $lineNumber,
                        'campo' => 'Quantidade vaga',
                        'erro' => "Linha {$lineNumber}: 'Quantidade vaga' deve ser maior ou igual a 1.",
                    ];
                }
            }

            // 4. Data início ('imediato' ou data específica)
            $inicioImediato = false;
            $dataInicio = null;
            if ($dataInicioRaw === '') {
                $rowErrors[] = [
                    'linha' => $lineNumber,
                    'campo' => 'Data início',
                    'erro' => "Linha {$lineNumber}: O campo 'Data início' é obrigatório (informe 'imediato' ou uma data válida DD/MM/AAAA).",
                ];
            } else {
                $normInicio = $this->normalizeKey($dataInicioRaw);
                if (in_array($normInicio, ['imediato', 'imediata'], true)) {
                    $inicioImediato = true;
                } else {
                    $parsedStart = $this->parseSpecificDate($dataInicioRaw);
                    if ($parsedStart === null) {
                        $rowErrors[] = [
                            'linha' => $lineNumber,
                            'campo' => 'Data início',
                            'erro' => "Linha {$lineNumber}: 'Data início' inválida ('{$dataInicioRaw}'). Use 'imediato' ou uma data válida (DD/MM/AAAA ou AAAA-MM-DD).",
                        ];
                    } else {
                        $dataInicio = $parsedStart;
                    }
                }
            }

            // 5. Data fim ('permanente' ou data específica)
            $fimPermanente = false;
            $dataFim = null;
            if ($dataFimRaw === '') {
                $rowErrors[] = [
                    'linha' => $lineNumber,
                    'campo' => 'Data fim',
                    'erro' => "Linha {$lineNumber}: O campo 'Data fim' é obrigatório (informe 'permanente' ou uma data válida DD/MM/AAAA).",
                ];
            } else {
                $normFim = $this->normalizeKey($dataFimRaw);
                if (in_array($normFim, ['permanente', 'indeterminado', 'indeterminada'], true)) {
                    $fimPermanente = true;
                } else {
                    $parsedEnd = $this->parseSpecificDate($dataFimRaw);
                    if ($parsedEnd === null) {
                        $rowErrors[] = [
                            'linha' => $lineNumber,
                            'campo' => 'Data fim',
                            'erro' => "Linha {$lineNumber}: 'Data fim' inválida ('{$dataFimRaw}'). Use 'permanente' ou uma data válida (DD/MM/AAAA ou AAAA-MM-DD).",
                        ];
                    } else {
                        $dataFim = $parsedEnd;
                    }
                }
            }

            if ($dataInicio !== null && $dataFim !== null && $dataFim->lt($dataInicio)) {
                $rowErrors[] = [
                    'linha' => $lineNumber,
                    'campo' => 'Data fim',
                    'erro' => "Linha {$lineNumber}: 'Data fim' ({$dataFim->format('d/m/Y')}) não pode ser anterior à 'Data início' ({$dataInicio->format('d/m/Y')}).",
                ];
            }

            // 6. Conhecimento (associado aos registros cadastrados no sistema)
            $matchedSkills = [];
            if ($conhecimentoRaw === '') {
                $rowErrors[] = [
                    'linha' => $lineNumber,
                    'campo' => 'Conhecimento',
                    'erro' => "Linha {$lineNumber}: O campo 'Conhecimento' é obrigatório. Informe conhecimentos cadastrados no sistema.",
                ];
            } else {
                $normFull = $this->normalizeKey($conhecimentoRaw);
                if (isset($catalogByNormalizedName[$normFull])) {
                    $validTokens = [trim($conhecimentoRaw)];
                } else {
                    // Prioritize '|' then ';' before ',' so skills containing commas (e.g. 'Logística, Almoxarifado e Estoque | Soldagem') are never split on inner commas
                    if (str_contains($conhecimentoRaw, '|')) {
                        $tokens = explode('|', $conhecimentoRaw);
                    } elseif (str_contains($conhecimentoRaw, ';')) {
                        $tokens = explode(';', $conhecimentoRaw);
                    } else {
                        $tokens = explode(',', $conhecimentoRaw);
                    }
                    $validTokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => $t !== ''));
                }

                if (empty($validTokens)) {
                    $rowErrors[] = [
                        'linha' => $lineNumber,
                        'campo' => 'Conhecimento',
                        'erro' => "Linha {$lineNumber}: Informe pelo menos um conhecimento válido do sistema.",
                    ];
                } else {
                    foreach ($validTokens as $token) {
                        $found = null;
                        if (ctype_digit($token) && isset($catalogById[(int) $token])) {
                            $found = $catalogById[(int) $token];
                        } else {
                            $normToken = $this->normalizeKey($token);
                            if (isset($catalogByNormalizedName[$normToken])) {
                                $found = $catalogByNormalizedName[$normToken];
                            }
                        }

                        if (!$found) {
                            $rowErrors[] = [
                                'linha' => $lineNumber,
                                'campo' => 'Conhecimento',
                                'erro' => "Linha {$lineNumber}: Conhecimento '{$token}' não encontrado na listagem do sistema.",
                            ];
                        } else {
                            $matchedSkills[$found->id] = $found;
                        }
                    }
                }
            }

            if (!empty($rowErrors)) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            $firstMatchedSkill = reset($matchedSkills);
            $categoria = ($firstMatchedSkill && !empty($firstMatchedSkill->categoria))
                ? (string) $firstMatchedSkill->categoria
                : 'geral';

            // Resolve municipality per row (by name or CEP) if provided, otherwise fallback to global
            $rowMunicipioId = $resolvedMunicipioId;
            if ($municipioRaw !== '') {
                $cepService = app(CepMunicipioService::class);
                $byCep = $cepService->resolveByCep($municipioRaw);
                if ($byCep) {
                    $rowMunicipioId = $byCep->id;
                } else {
                    $normMun = $this->normalizeKey($municipioRaw);
                    foreach (MunicipioEs::all() as $m) {
                        if ($this->normalizeKey($m->nome) === $normMun || (string) $m->codigo_ibge === $municipioRaw) {
                            $rowMunicipioId = $m->id;
                            break;
                        }
                    }
                }
            }

            $validatedRows[] = [
                'titulo' => $nomeVaga,
                'descricao' => $descricaoVaga,
                'vagas_totais' => $quantidade,
                'inicio_imediato' => $inicioImediato,
                'data_inicio' => $dataInicio?->toDateString(),
                'fim_permanente' => $fimPermanente,
                'data_fim' => $dataFim?->toDateString(),
                'categoria' => mb_substr($categoria, 0, 50),
                'conhecimento_ids' => array_keys($matchedSkills),
                'municipio_id' => $rowMunicipioId,
            ];
        }

        fclose($stream);

        if ($dataRowCount === 0) {
            return [
                'ok' => false,
                'http_status' => 422,
                'payload' => [
                    'status' => 'validation_error',
                    'error' => 'Nenhuma linha de vaga encontrada no arquivo CSV além do cabeçalho.',
                    'message' => 'Nenhuma linha de vaga encontrada no arquivo CSV além do cabeçalho.',
                    'total_registros' => 0,
                    'sucesso_count' => 0,
                    'erro_count' => 1,
                    'erros' => [
                        ['linha' => 2, 'campo' => 'dados', 'erro' => 'O arquivo CSV não possui vagas preenchidas para importação.'],
                    ],
                ],
            ];
        }

        if (!empty($errors)) {
            return [
                'ok' => false,
                'http_status' => 422,
                'payload' => [
                    'status' => 'validation_error',
                    'error' => 'Foram encontrados erros de validação no arquivo CSV. Corrija as linhas indicadas e tente novamente.',
                    'message' => 'Foram encontrados erros de validação no arquivo CSV. Nenhuma vaga foi gravada.',
                    'total_registros' => $dataRowCount,
                    'sucesso_count' => 0,
                    'erro_count' => count($errors),
                    'erros' => $errors,
                ],
            ];
        }

        $createdVagas = [];

        DB::transaction(function () use ($validatedRows, $user, $resolvedMunicipioId, &$createdVagas) {
            foreach ($validatedRows as $rowData) {
                $vaga = VagaEmprego::create([
                    'user_id' => $user->id,
                    'empresa' => $user->name,
                    'titulo' => $rowData['titulo'],
                    'descricao' => $rowData['descricao'],
                    'categoria' => $rowData['categoria'],
                    'municipio_id' => $rowData['municipio_id'] ?? $resolvedMunicipioId,
                    'regime_contratacao' => 'CLT',
                    'afirmativa_egresso' => true,
                    'empresa_amiga_reintegracao' => true,
                    'vagas_totais' => $rowData['vagas_totais'],
                    'vagas_preenchidas' => 0,
                    'inicio_imediato' => $rowData['inicio_imediato'],
                    'data_inicio' => $rowData['data_inicio'],
                    'fim_permanente' => $rowData['fim_permanente'],
                    'data_fim' => $rowData['data_fim'],
                    'status' => 'aberta',
                ]);

                $vaga->conhecimentos()->sync($rowData['conhecimento_ids']);
                $vaga->load(['municipio:id,codigo_ibge,nome,macrorregiao', 'conhecimentos:id,nome,categoria']);
                $createdVagas[] = $vaga;
            }
        });

        $count = count($createdVagas);

        return [
            'ok' => true,
            'http_status' => 201,
            'payload' => [
                'status' => 'imported',
                'message' => "{$count} " . ($count === 1 ? 'vaga importada com sucesso.' : 'vagas importadas com sucesso.'),
                'total_registros' => $dataRowCount,
                'sucesso_count' => $count,
                'erro_count' => 0,
                'erros' => [],
                'data' => array_map(function (VagaEmprego $v) {
                    return [
                        'id' => $v->id,
                        'empresa' => $v->empresa,
                        'titulo' => $v->titulo,
                        'descricao' => $v->descricao,
                        'vagas_totais' => $v->vagas_totais,
                        'inicio_imediato' => (bool) $v->inicio_imediato,
                        'data_inicio' => $v->data_inicio?->format('Y-m-d'),
                        'fim_permanente' => (bool) $v->fim_permanente,
                        'data_fim' => $v->data_fim?->format('Y-m-d'),
                        'municipio' => $v->municipio,
                        'conhecimentos' => $v->conhecimentos->map(fn($c) => [
                            'id' => $c->id,
                            'nome' => $c->nome,
                            'categoria' => $c->categoria,
                        ])->values()->all(),
                    ];
                }, $createdVagas),
            ],
        ];
    }

    /**
     * Auto-detect CSV field delimiter (';', ',', or "\t") from the first non-empty header line.
     */
    protected function detectDelimiter(string $content): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $headerLine = '';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $headerLine = $line;
                break;
            }
        }

        $semicolons = substr_count($headerLine, ';');
        $commas = substr_count($headerLine, ',');
        $tabs = substr_count($headerLine, "\t");

        if ($tabs > $semicolons && $tabs > $commas) {
            return "\t";
        }

        return $semicolons >= $commas ? ';' : ',';
    }

    /**
     * Map normalized header labels to column indexes.
     */
    protected function mapHeaderIndexes(array $headerRow): array
    {
        $aliases = [
            'nome_vaga' => ['nome vaga', 'nome da vaga', 'titulo', 'titulo da vaga', 'cargo', 'vaga'],
            'descricao_vaga' => ['descricao vaga', 'descricao da vaga', 'descricao'],
            'quantidade_vaga' => ['quantidade vaga', 'quantidade de vagas', 'quantidade vagas', 'quantidade', 'qtd', 'vagas'],
            'data_inicio' => ['data inicio', 'data de inicio', 'inicio'],
            'data_fim' => ['data fim', 'data de fim', 'fim', 'data termino', 'termino'],
            'conhecimento' => ['conhecimento', 'conhecimentos', 'habilidade', 'habilidades', 'competencia', 'competencias'],
            'municipio' => ['municipio', 'cidade', 'local', 'cep', 'municipio / cep', 'cidade / cep'],
        ];

        $mapped = [];

        foreach ($headerRow as $idx => $rawHeader) {
            $cleaned = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader) ?? (string) $rawHeader;
            $norm = $this->normalizeKey($cleaned);

            foreach ($aliases as $canonical => $acceptedList) {
                if (!isset($mapped[$canonical]) && in_array($norm, $acceptedList, true)) {
                    $mapped[$canonical] = $idx;
                    break;
                }
            }
        }

        return $mapped;
    }

    /**
     * Parse DD/MM/YYYY, DD-MM-YYYY, or YYYY-MM-DD with strict calendar checkdate validation.
     */
    protected function parseSpecificDate(string $value): ?Carbon
    {
        $trimmed = trim($value);

        // DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $trimmed, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];

            if (!checkdate($month, $day, $year)) {
                return null;
            }

            return Carbon::createFromDate($year, $month, $day)->startOfDay();
        }

        // YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];

            if (!checkdate($month, $day, $year)) {
                return null;
            }

            return Carbon::createFromDate($year, $month, $day)->startOfDay();
        }

        return null;
    }

    /**
     * Normalize strings for accent-insensitive and case-insensitive matching.
     */
    protected function normalizeKey(string $input): string
    {
        $str = mb_strtolower(trim($input), 'UTF-8');
        $accents = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        $str = strtr($str, $accents);
        $str = preg_replace('/\s+/', ' ', $str) ?? $str;

        return trim($str);
    }

    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    protected function resolveMunicipioId(?int $requestedMunicipioId, User $user): int
    {
        if ($requestedMunicipioId) {
            $found = MunicipioEs::where('id', $requestedMunicipioId)
                ->orWhere('codigo_ibge', $requestedMunicipioId)
                ->value('id');
            if ($found) {
                return (int) $found;
            }
        }

        if ($user->municipio_id) {
            return (int) $user->municipio_id;
        }

        $vitoriaId = MunicipioEs::where('codigo_ibge', 3205309)->value('id')
            ?? MunicipioEs::orderBy('id', 'asc')->value('id');

        if ($vitoriaId) {
            return (int) $vitoriaId;
        }

        // Fallback if municipios_es was not seeded in an isolated test
        $fallback = MunicipioEs::create([
            'codigo_ibge' => 3205309,
            'nome' => 'Vitória',
            'microrregiao' => 'Metropolitana',
            'macrorregiao' => 'Metropolitana',
            'tem_escritorio_fisico' => true,
            'latitude' => -20.3155,
            'longitude' => -40.3128,
            'populacao_estimada' => 322869,
        ]);

        return (int) $fallback->id;
    }
}
