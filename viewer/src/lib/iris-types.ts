export type IrisUiState = "idle" | "thinking" | "responding";

export type IrisSuccessResponse =
  | { type: "message"; message: string }
  | { type: "redirect"; target: string };

export const IRIS_UNEXPECTED_RESPONSE: IrisSuccessResponse = {
  type: "message",
  message: "Unexpected response",
};

export function parseIrisResponse(data: unknown): IrisSuccessResponse | null {
  if (!data || typeof data !== "object") return null;
  const o = data as Record<string, unknown>;
  if (o.type === "message" && typeof o.message === "string") {
    return { type: "message", message: o.message };
  }
  if (o.type === "redirect" && typeof o.target === "string") {
    return { type: "redirect", target: o.target };
  }
  return null;
}

/** Never returns null — safe for KAEL/upstream garbage without breaking the UI. */
export function safeIrisPayload(data: unknown): IrisSuccessResponse {
  return parseIrisResponse(data) ?? IRIS_UNEXPECTED_RESPONSE;
}
