import { safeIrisPayload, type IrisSuccessResponse } from "@/lib/iris-types";
import { NextResponse } from "next/server";

const UPSTREAM_TIMEOUT_MS = 25_000;

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
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON body" }, { status: 400 });
  }

  if (!body || typeof body !== "object") {
    return NextResponse.json({ error: "Expected JSON object" }, { status: 400 });
  }

  const trimmed = readPrompt(body as Record<string, unknown>);
  if (trimmed === null) {
    return NextResponse.json(
      { error: "Field `prompt` or `input` must be a non-empty string" },
      { status: 400 },
    );
  }

  const upstream = process.env.IRIS_UPSTREAM_URL;

  if (upstream) {
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
        body: JSON.stringify({ input: trimmed }),
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
  }

  const stub: IrisSuccessResponse = {
    type: "message",
    message: `I heard: “${trimmed.slice(0, 200)}${trimmed.length > 200 ? "…" : ""}”. Iris stub is active — set IRIS_UPSTREAM_URL to reach your real service.`,
  };
  return NextResponse.json(stub);
}
