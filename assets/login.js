// Mapa de puntos con rutas animadas (estilo "Travel Connect")
(function () {
  const canvas = document.getElementById('tc-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  // Rutas en coordenadas relativas (0..1)
  const routes = [
    [[0.18, 0.32], [0.38, 0.22]],
    [[0.38, 0.22], [0.58, 0.30]],
    [[0.12, 0.18], [0.30, 0.48]],
    [[0.52, 0.12], [0.74, 0.26]],
    [[0.30, 0.48], [0.48, 0.62]],
    [[0.62, 0.42], [0.84, 0.58]],
    [[0.24, 0.70], [0.50, 0.80]],
  ];

  let dots = [], w = 0, h = 0, start = performance.now();

  function inLand(x, y) {
    // Silueta aproximada de continentes
    return (x < 0.25 && y > 0.10 && y < 0.40) ||
      (x > 0.20 && x < 0.30 && y > 0.40 && y < 0.82) ||
      (x > 0.42 && x < 0.52 && y > 0.10 && y < 0.36) ||
      (x > 0.44 && x < 0.56 && y > 0.36 && y < 0.70) ||
      (x > 0.52 && x < 0.80 && y > 0.10 && y < 0.50) ||
      (x > 0.72 && x < 0.88 && y > 0.58 && y < 0.78);
  }

  function resize() {
    const dpr = window.devicePixelRatio || 1;
    w = canvas.clientWidth; h = canvas.clientHeight;
    canvas.width = w * dpr; canvas.height = h * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    dots = [];
    for (let x = 0; x < w; x += 12) for (let y = 0; y < h; y += 12) {
      if (inLand(x / w, y / h) && Math.random() > 0.3) dots.push([x, y, Math.random() * 0.5 + 0.2]);
    }
  }

  function frame(now) {
    ctx.clearRect(0, 0, w, h);
    for (const [x, y, a] of dots) {
      ctx.beginPath(); ctx.arc(x, y, 1, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(35,119,101,${a})`; ctx.fill();
    }
    const t = (now - start) / 1000, cycle = routes.length * 0.5 + 3;
    const local = t % cycle;
    routes.forEach(([[ax, ay], [bx, by]], i) => {
      const p = Math.min(Math.max((local - i * 0.5) / 2, 0), 1);
      if (p <= 0) return;
      const x1 = ax * w, y1 = ay * h, x2 = bx * w, y2 = by * h;
      const cx = (x1 + x2) / 2, cy = Math.min(y1, y2) - 40;
      ctx.beginPath(); ctx.moveTo(x1, y1);
      // Curva parcial
      const steps = 40;
      let px = x1, py = y1;
      for (let s = 1; s <= steps * p; s++) {
        const u = s / steps;
        px = (1 - u) ** 2 * x1 + 2 * (1 - u) * u * cx + u * u * x2;
        py = (1 - u) ** 2 * y1 + 2 * (1 - u) * u * cy + u * u * y2;
        ctx.lineTo(px, py);
      }
      const g = ctx.createLinearGradient(x1, y1, x2, y2);
      g.addColorStop(0, 'rgba(110,190,165,.55)'); g.addColorStop(1, 'rgba(35,119,101,.55)');
      ctx.strokeStyle = g; ctx.lineWidth = 1.5; ctx.stroke();
      [[x1, y1], p >= 1 ? [x2, y2] : null].forEach(pt => {
        if (!pt) return;
        ctx.beginPath(); ctx.arc(pt[0], pt[1], 3, 0, Math.PI * 2);
        ctx.fillStyle = '#237765'; ctx.fill();
        ctx.beginPath(); ctx.arc(pt[0], pt[1], 6, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(35,119,101,.25)'; ctx.fill();
      });
      if (p < 1) {
        ctx.beginPath(); ctx.arc(px, py, 3.5, 0, Math.PI * 2);
        ctx.fillStyle = '#237765'; ctx.fill();
      }
    });
    requestAnimationFrame(frame);
  }

  resize();
  window.addEventListener('resize', resize);
  requestAnimationFrame(frame);

  // Mostrar/ocultar contraseña
  const eye = document.getElementById('tc-eye'), pw = document.getElementById('tc-password');
  if (eye && pw) eye.addEventListener('click', () => {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    eye.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    eye.style.color = show ? '#237765' : '';
    eye.innerHTML = show ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
  });
})();
