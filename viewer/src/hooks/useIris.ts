"use client";

import {
  parseIrisResponse,
  type IrisSuccessResponse,
  type IrisUiState,
} from "@/lib/iris-types";
import { useCallback, useRef, useState } from "react";

const REDIRECT_DELAY_MS = 700;
const RESPONDING_TO_IDLE_MS = 1200;

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
        applyState("responding", "Redirecting...");
        timerRef.current = setTimeout(() => {
          timerRef.current = null;
          window.location.href = data.target;
        }, REDIRECT_DELAY_MS);
        return;
      }
      if (data.type === "message") {
        applyState("responding", data.message);
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
        const res = await fetch("/api/iris", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ prompt }),
        });

        let data: unknown;
        try {
          data = await res.json();
        } catch (err) {
          console.error(err);
          applyState("idle", "Unexpected response");
          return "kept";
        }

        const parsed = parseIrisResponse(data);
        if (parsed) {
          handleIrisResponse(parsed);
          return "cleared";
        }

        applyState("idle", "Unexpected response");
        return "kept";
      } catch (err) {
        console.error(err);
        applyState("idle", "System error");
        return "kept";
      } finally {
        setInputLocked(false);
      }
    },
    [applyState, clearTimer, handleIrisResponse],
  );

  return { irisState, statusText, inputLocked, send };
}
