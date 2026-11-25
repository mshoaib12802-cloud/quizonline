const revealItems = document.querySelectorAll(
  ".reveal, .feature-card, .image-feature",
);

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.15 },
);

revealItems.forEach((item) => {
  item.classList.add("reveal");
  observer.observe(item);
});
