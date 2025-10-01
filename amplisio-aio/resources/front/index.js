import './style.css';

const applyAccentColor = () => {
  if (!window.amplisioAioFront || !window.amplisioAioFront.accentColor) {
    return;
  }

  document.documentElement.style.setProperty('--amplisio-accent', window.amplisioAioFront.accentColor);
};

document.addEventListener('DOMContentLoaded', applyAccentColor);
