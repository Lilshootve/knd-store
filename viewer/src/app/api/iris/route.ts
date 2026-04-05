import { safeIrisPayload, type IrisSuccessResponse } from "@/lib/iris-types";
import { NextResponse } from "next/server";

const UPSTREAM_TIMEOUT_MS = 25_000;

function invalidRequest(): NextResponse {
  return NextResponse.json(
    { type: "message", message: "Invalid request" } satisfies IrisSuccessResponse,
    { status: 400 },
  );
}

function systemUnavailable(): NextResponse {
  return NextResponse.json(
    { type: "message", message: "System unavailable" } satisfies IrisSuccessResponse,
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

/** Temporary KAEL-lite simulation when IRIS_UPSTREAM_URL is unset. */
function irisMockDecision(input: string): IrisSuccessResponse {
  const lower = input.toLowerCase();

  if (["image", "draw", "generate", "art"].some((k) => lower.includes(k))) {
    return {
      type: "redirect",
      target: `/knd-labs?prompt=${encodeURIComponent(input)}`,
    };
  }
  if (["play", "game", "battle"].some((k) => lower.includes(k))) {
    return { type: "redirect", target: "/knd-games" };
  }
  if (["buy", "product", "store"].some((k) => lower.includes(k))) {
    return {
      type: "redirect",
      target: `/store?search=${encodeURIComponent(input)}`,
    };
  }

  return {
    type: "message",
    message: "Iris understood your request but no direct action was triggered.",
  };
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

    return NextResponse.json(irisMockDecision(trimmed));
  } catch {
    return systemUnavailable();
  }
}
