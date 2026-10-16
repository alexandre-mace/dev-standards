export const slugify = (text: string): string =>
  text.normalize('NFD').replace(/[̀-ͯ]/g, '');
