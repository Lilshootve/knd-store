import { safeIrisPayload, type IrisSuccessResponse } from "@/lib/iris-types";
import { NextResponse } from "next/server";

const UPSTREAM_TIMEOUT_MS = 25_000;

function invalidRequest(): NextResponse {
  return NextResponse.json(
    { type: "chat", response: "Invalid request" } satisfies IrisSuccessResponse,
    { status: 400 },
  );
}

function systemUnavailable(): NextResponse {
  return NextResponse.json(
    { type: "chat", response: "System unavailable" } satisfies IrisSuccessResponse,
    { status: 500 },
  );
}

function readPrompt(body: Record<string, unknown>): string | null {
  const prompt = body.prompt;
  const input = body.input;
  if (typeof prompt === "string" && prompt.trim().length > 0) {
    return prompt.trim();
  }
  if (typeof input === "string" && input.trim().length > 0) {
    return input.trim();
  }
  return null;
}

export async function POST(request: Request) {
  try {
    let body: unknown;
    try {
      body = await request.json();
    } catch {
      return invalidRequest();
    }

    if (!body || typeof body !== "object") {
      return invalidRequest();
    }

    const trimmed = readPrompt(body as Record<string, unknown>);
    if (trimmed === null) {
      return invalidRequest();
    }

    const upstream = process.env.IRIS_UPSTREAM_URL;

    if (!upstream) {
      return NextResponse.json(
        { type: "chat", response: "System unavailable" } satisfies IrisSuccessResponse,
        { status: 503 },
      );
    }

    try {
      const headers: HeadersInit = {
        "Content-Type": "application/json",
      };
      const apiKey = process.env.IRIS_UPSTREAM_API_KEY;
      if (apiKey) {
        headers["x-api-key"] = apiKey;
      }
      const auth = process.env.IRIS_UPSTREAM_AUTH_HEADER;
      if (auth) {
        headers.Authorization = auth;
      }

      const res = await fetch(upstream, {
        method: "POST",
        headers,
        body: JSON.stringify({
          message: trimmed,
          context: { includeLastRun: true },
          conversation_history: [],
        }),
        signal: AbortSignal.timeout(UPSTREAM_TIMEOUT_MS),
      });

      const text = await res.text();
      let json: unknown;
      try {
        json = JSON.parse(text) as unknown;
      } catch {
        return NextResponse.json(safeIrisPayload(null));
      }

      return NextResponse.json(safeIrisPayload(json));
    } catch {
      return NextResponse.json(safeIrisPayload(null));
    }
  } catch {
    return systemUnavailable();
  }
}
