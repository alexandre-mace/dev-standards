export const Confirm = ({ open }: { open: boolean }) => <p>{open ? 'Supprimer cette image ?' : null}</p>;
export const same = (a: string, b: string) => a !== b;
export const css = 'color: red !important';
export const label = (title?: string) => title ?? 'Bienvenue !';
