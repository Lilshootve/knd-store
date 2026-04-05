"use client";

import { cn } from "@/lib/utils";
import type { ChangeEvent, KeyboardEvent } from "react";

type IrisInputProps = {
  value: string;
  onChange: (value: string) => void;
  onSubmit: () => void;
  disabled?: boolean;
  /** Visual + pointer feedback while the request is in flight */
  locked?: boolean;
  placeholder?: string;
};

export function IrisInput({
  value,
  onChange,
  onSubmit,
  disabled,
  locked,
  placeholder = "Tell Iris what you want to do...",
}: IrisInputProps) {
  return (
    <div
      className={cn("knd-iris-input-wrap", locked && "knd-iris-input-wrap--locked")}
    >
      <input
        className="knd-iris-input"
        type="text"
        value={value}
        disabled={disabled}
        placeholder={placeholder}
        onChange={(e: ChangeEvent<HTMLInputElement>) => onChange(e.target.value)}
        onKeyDown={(e: KeyboardEvent<HTMLInputElement>) => {
          if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            if (!disabled) onSubmit();
          }
        }}
        aria-label={placeholder}
        aria-busy={locked}
      />
    </div>
  );
}
