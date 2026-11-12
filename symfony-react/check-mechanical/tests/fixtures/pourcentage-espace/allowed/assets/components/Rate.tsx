// Un taux à 2,5 % s'affichait « 3 % ».
export const label = (rate: string) => `${rate}\u00a0%`;
export const even = (index: number, x1: number) => index % 2 === 0 && x1 % 3 === 0;
export const width = 'calc(100% - 2rem)';
export const Rate = () => <p>Bénéficiez de 1 % en parts offertes.</p>;
