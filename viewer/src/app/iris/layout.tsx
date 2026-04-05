import type { Metadata } from "next";
import "./knd-iris.css";

export const metadata: Metadata = {
  title: "Iris — KND Agents",
  description: "Interface layer for KND Agents",
};

export default function IrisLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="dark knd-iris knd-iris-bg min-h-[100dvh]">
      {children}
    </div>
  );
}
