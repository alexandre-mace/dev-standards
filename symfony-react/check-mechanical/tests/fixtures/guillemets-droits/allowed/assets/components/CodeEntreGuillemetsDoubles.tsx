import React from "react";
import { cn } from "@/lib/utils";

export const unite = { unit: "kWh", label: "Consommation" };
export const tous = (filtre: string) => (filtre === "all" ? "tous" : filtre);
export function libelle(statut: string): string {
  switch (statut) {
    case "draft":
      return "Brouillon";
    default:
      throw new Error("Statut inconnu");
  }
}
export const Ligne = ({ actif }: { actif: boolean }) => (
  <p className={cn("text-sm", actif && "font-medium")}>{React.version}</p>
);
