# KND Store - GPU Server (InstantMesh)
# Base: PyTorch CUDA (InstantMesh needs torch, diffusers, etc.)
FROM pytorch/pytorch:2.1.0-cuda12.1-cudnn8-runtime

ENV DEBIAN_FRONTEND=noninteractive
ENV PYTHONUNBUFFERED=1

# Install system deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    libgl1-mesa-glx \
    libglib2.0-0 \
    ffmpeg \
    wget \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy GPU server code
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY server.py worker.py ./

# Clone InstantMesh
RUN git clone --depth 1 https://github.com/TencentARC/InstantMesh.git /app/InstantMesh

WORKDIR /app/InstantMesh
RUN pip install --no-cache-dir -r requirements.txt

# Back to app root
WORKDIR /app

# Workspace
RUN mkdir -p /workspace/jobs /workspace/models

ENV WORKSPACE_DIR=/workspace
ENV MODELS_DIR=/workspace/models
ENV INSTANTMESH_DIR=/app/InstantMesh
ENV GPU_HOST_BASE_URL=http://localhost:8000
ENV INSTANTMESH_CALLBACK_SECRET=
ENV TRIPOSR_CALLBACK_SECRET=
ENV MAX_CONCURRENCY=2
ENV INSTANTMESH_CONFIG=configs/instant-mesh-large.yaml

EXPOSE 8000

CMD ["uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8000"]
