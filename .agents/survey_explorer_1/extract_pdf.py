import zlib, re

with open('TR_EDITAL_DE CPSI Nº 010_2026 - SEJUS.pdf', 'rb') as f:
    content = f.read()

streams = re.findall(rb'stream[\r\n]+(.*?)[\r\n]+endstream', content, re.DOTALL)
print(f'Total streams: {len(streams)}')

all_decompressed = []
for i, s in enumerate(streams):
    try:
        d = zlib.decompress(s)
        all_decompressed.append(d)
    except:
        pass

print(f'Decompressed {len(all_decompressed)} streams')

tounicodes = {}
for d in all_decompressed:
    if b'beginbfchar' in d or b'beginbfrange' in d:
        lines = d.splitlines()
        for line in lines:
            line_str = line.decode('latin-1', errors='ignore').strip()
            # single char mapping: <0001> <0041>
            m = re.match(r'<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>', line_str)
            if m:
                src, dst = m.groups()
                try:
                    uni_char = bytes.fromhex(dst).decode('utf-16-be')
                    tounicodes[src.upper().zfill(4)] = uni_char
                except:
                    pass
            # range mapping: <0001> <0005> <0041>
            m2 = re.match(r'<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>', line_str)
            if m2:
                s_start, s_end, d_start = m2.groups()
                try:
                    start_code = int(s_start, 16)
                    end_code = int(s_end, 16)
                    dest_code = int(d_start, 16)
                    for c in range(start_code, end_code + 1):
                        diff = c - start_code
                        src_hex = f'{c:04X}'
                        dst_char = chr(dest_code + diff)
                        tounicodes[src_hex] = dst_char
                except:
                    pass

print(f'Cmap mappings found: {len(tounicodes)}')

full_text = []
for d in all_decompressed:
    lines = d.splitlines()
    for l in lines:
        l_str = l.decode('latin-1', errors='ignore')
        hex_matches = re.findall(r'<([0-9a-fA-F]+)>', l_str)
        if hex_matches and ('Tj' in l_str or 'TJ' in l_str):
            chars = []
            for h in hex_matches:
                for k in range(0, len(h), 4):
                    chunk = h[k:k+4].upper().zfill(4)
                    if chunk in tounicodes:
                        chars.append(tounicodes[chunk])
                    else:
                        try:
                            chars.append(bytes.fromhex(chunk).decode('utf-16-be'))
                        except:
                            pass
            if chars:
                full_text.append(''.join(chars))

extracted_text = '\n'.join(full_text)
print('Total decoded lines:', len(full_text))
print('Sample text:')
print(extracted_text[:2000])

with open('.agents/survey_explorer_1/tr_extracted_full.txt', 'w', encoding='utf-8') as out:
    out.write(extracted_text)
