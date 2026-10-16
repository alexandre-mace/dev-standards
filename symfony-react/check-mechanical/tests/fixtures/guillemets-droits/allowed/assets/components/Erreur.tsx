import { cn } from '@/lib/utils';

// Le bouton "Valider" d'un commentaire n'est pas affiché.
export const message = (name: string) => `Impossible de lire le PDF « ${name} ».`;
export const Aide = ({ plusieurs }: { plusieurs: boolean }) => (
  <p className="text-sm" aria-label={plusieurs ? 'les autres aides' : "l'autre aide"}>
    {cn('a', plusieurs && 'b')} Clique sur « Valider ».
  </p>
);
export const selecteur = '[data-step="2"]';
