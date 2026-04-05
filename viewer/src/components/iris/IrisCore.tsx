"use client";

import { useIris } from "@/hooks/useIris";
import { cn } from "@/lib/utils";
import { useState } from "react";
import { IrisInput } from "./IrisInput";

function IrisHexagon() {
  return (
    <div className="knd-iris-hex-wrap" aria-hidden>
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="iris-hex-fill" x1="40" y1="30" x2="170" y2="180" gradientUnits="userSpaceOnUse">
            <stop stopColor="#7c5cff" stopOpacity="0.25" />
            <stop offset="0.55" stopColor="#00d4ff" stopOpacity="0.12" />
            <stop offset="1" stopColor="#7c5cff" stopOpacity="0.08" />
          </linearGradient>
          <linearGradient id="iris-hex-stroke" x1="30" y1="100" x2="170" y2="100" gradientUnits="userSpaceOnUse">
            <stop stopColor="#7c5cff" />
            <stop offset="1" stopColor="#00d4ff" />
          </linearGradient>
        </defs>
        <g className="knd-iris-hex-glow" style={{ transformOrigin: "100px 100px" }}>
          <polygon
            points="170,100 135,160.62 65,160.62 30,100 65,39.38 135,39.38"
            fill="url(#iris-hex-fill)"
            stroke="url(#iris-hex-stroke)"
            strokeWidth="1.5"
          />
        </g>
      </svg>
    </div>
  );
}

export function IrisCore() {
  const { irisState, statusText, inputLocked, send } = useIris();
  const [draft, setDraft] = useState("");

  const submit = async () => {
    const text = draft.trim();
    if (!text) return;
    const result = await send(text);
    if (result === "cleared") {
      setDraft("");
    }
  };

  return (
    <div className="relative flex min-h-[100dvh] flex-1 flex-col">
      <div className="knd-iris-grid pointer-events-none absolute inset-0" />
      <div className="relative z-[1] mx-auto flex w-full max-w-lg flex-1 flex-col items-center justify-center px-6 pb-16 pt-20">
        <div className={cn("knd-iris-core flex flex-col items-center", irisState)}>
          <IrisHexagon />
        </div>
        <p
          className="mt-8 max-w-md whitespace-pre-wrap text-center text-sm leading-relaxed tracking-wide"
          style={{ color: "var(--knd-iris-text-muted)" }}
        >
          {statusText}
        </p>

        <div className="mt-12 w-full">
          <IrisInput
            value={draft}
            onChange={setDraft}
            onSubmit={submit}
            disabled={inputLocked}
            locked={inputLocked}
          />
        </div>
      </div>
    </div>
  );
}
