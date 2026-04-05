export type IrisUiState = "idle" | "thinking" | "responding";

export type IrisSuccessResponse =
  | { type: "chat"; response: string }
  | { type: "redirect"; target: string };

export const IRIS_UNEXPECTED_RESPONSE: IrisSuccessResponse = {
  type: "chat",
  response: "System unavailable",
};

export function parseIrisResponse(data: unknown): IrisSuccessResponse | null {
  if (!data || typeof data !== "object") return null;
  const o = data as Record<string, unknown>;

  if (o.type === "chat" && typeof o.response === "string") {
    return { type: "chat", response: o.response };
  }

  if (o.type === "redirect") {
    const r = o.redirect;
    if (r && typeof r === "object") {
      const t = (r as Record<string, unknown>).target;
      if (typeof t === "string" && t.length > 0) {
        return { type: "redirect", target: t };
      }
    }
  }

  return null;
}

/** Never returns null — safe for upstream garbage without breaking the UI. */
export function safeIrisPayload(data: unknown): IrisSuccessResponse {
  return parseIrisResponse(data) ?? IRIS_UNEXPECTED_RESPONSE;
}
