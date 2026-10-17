import { useState } from 'react';

type Label = { text: string };

export const A = ({ props }) => {
  const [label] = useState<Label>({ text: 'x' });
  return (
    <>
      <Label htmlFor="nom">Nom</Label>
      <Input id="nom" />
      <Label className="flex gap-2">
        <Checkbox /> Accepter
      </Label>
      <Label id="groupe-avatars">Avatars</Label>
      <div role="radiogroup" aria-labelledby="groupe-avatars" />
      <Label {...props}>{label.text}</Label>
    </>
  );
};
