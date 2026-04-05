"use client";

import {
  parseIrisResponse,
  type IrisSuccessResponse,
  type IrisUiState,
} from "@/lib/iris-types";
import { useCallback, useRef, useState } from "react";

const IRIS_CHAT_URL = "http://127.0.0.1:3000/api/iris/chat";
const RESPONDING_TO_IDLE_MS = 1200;
const FALLBACK = "System unavailable";

export type IrisSendResult = "cleared" | "kept";

/**
 * Mirrors the Iris state machine: explicit `message` updates ref + status;
 * omitted `message` (default null) uses fallbacks (idle → last message or ready).
 */
export function useIris() {
  const [irisState, setIrisState] = useState<IrisUiState>("idle");
  const [statusText, setStatusText] = useState("Iris is ready");
  const [inputLocked, setInputLocked] = useState(false);

  const currentMessageRef = useRef("");
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const clearTimer = useCallback(() => {
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  const applyState = useCallback((state: IrisUiState, message?: string | null) => {
    setIrisState(state);
    if (typeof message === "string") {
      currentMessageRef.current = message;
      setStatusText(message);
      return;
    }
    if (state === "thinking") {
      setStatusText("Thinking...");
      return;
    }
    if (state === "idle") {
      setStatusText(currentMessageRef.current || "Iris is ready");
    }
  }, []);

  const handleIrisResponse = useCallback(
    (data: IrisSuccessResponse) => {
      if (data.type === "redirect") {
        window.location.href = data.target;
        return;
      }
      if (data.type === "chat") {
        applyState("responding", data.response);
        timerRef.current = setTimeout(() => {
          timerRef.current = null;
          applyState("idle");
        }, RESPONDING_TO_IDLE_MS);
      }
    },
    [applyState],
  );

  const send = useCallback(
    async (raw: string): Promise<IrisSendResult> => {
      const prompt = raw.trim();
      if (!prompt) return "kept";

      clearTimer();
      applyState("thinking");
      setInputLocked(true);

      try {
        const res = await fetch(IRIS_CHAT_URL, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            message: prompt,
            context: { includeLastRun: true },
            conversation_history: [],
          }),
        });

        let data: unknown;
        try {
          data = await res.json();
        } catch {
          applyState("idle", FALLBACK);
          return "kept";
        }

        const parsed = parseIrisResponse(data);
        if (parsed && res.ok) {
          handleIrisResponse(parsed);
          return "cleared";
        }

        applyState("idle", FALLBACK);
        return "kept";
      } catch {
        applyState("idle", FALLBACK);
        return "kept";
      } finally {
        setInputLocked(false);
      }
    },
    [applyState, clearTimer, handleIrisResponse],
  );

  return { irisState, statusText, inputLocked, send };
}
