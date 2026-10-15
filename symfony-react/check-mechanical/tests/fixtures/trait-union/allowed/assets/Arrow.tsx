export const A = ({ page, go }) => (
  <button
    onClick={() =>
      go(page - 1)
    }
  >
    x
  </button>
);
