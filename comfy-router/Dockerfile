# KND ComfyUI Router - runs alongside ComfyUI
# ComfyUI must run separately (e.g. in another container or host)
FROM python:3.11-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY server.py worker.py comfy_client.py workflow_loader.py ./
COPY workflows/ ./workflows/

ENV COMFYUI_URL=http://host.docker.internal:8188
ENV ROUTER_BASE_URL=http://localhost:8000
ENV AI_CALLBACK_SECRET=
ENV MAX_ACTIVE_JOBS=2
ENV MAX_QUEUE_LENGTH=50

EXPOSE 8000

CMD ["uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8000"]
