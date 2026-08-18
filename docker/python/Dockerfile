# ==============================================================================
# CONECTA EGRESSO (SEJUS/ES) - Python 3.12 WebRTC Signaling Microservice
# ==============================================================================
FROM python:3.12-slim-bookworm

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    DEBIAN_FRONTEND=noninteractive

# Instalacao de bibliotecas essenciais para aiortc, pyjwt e criptografia
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libffi-dev \
    libssl-dev \
    libopus-dev \
    libvpx-dev \
    libavformat-dev \
    libavcodec-dev \
    libavutil-dev \
    libswscale-dev \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Criacao de usuario nao-root
RUN groupadd -g 1000 appuser && \
    useradd -u 1000 -g appuser -m -s /bin/bash appuser

WORKDIR /app

# Instalacao previa de dependencias Python
COPY webrtc_service/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r /app/requirements.txt

# Copia do codigo do microsservico
COPY webrtc_service /app

RUN chown -R appuser:appuser /app

USER appuser

EXPOSE 8001

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001", "--workers", "2", "--log-level", "info"]
