<button
  className={cn(
    'rounded-md outline-none',
    'focus-visible:ring-2 focus-visible:ring-ring',
  )}
/>
<h2 className="text-lg outline-none" tabIndex={-1}>Titre</h2>
<h2
  className="text-lg outline-none"
  tabIndex={focusTarget ? -1 : undefined}
>
  Titre
</h2>
const headingProps = {
  tabIndex: headingRef ? -1 : undefined,
  className: 'text-base outline-none',
};
