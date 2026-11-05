// Red animada de conexiones para el panel de acceso.
(function () {
  const canvas = document.getElementById('tc-canvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const map = canvas.parentElement;
  const mapText = map.querySelector('.tc-map-text');
  const compactViewport = window.matchMedia('(max-width: 768px)');

  let nodes = [];
  let backgroundDots = [[], [], []];
  let obstacles = [];
  let width = 0;
  let height = 0;
  let lastFrame = 0;
  let lastObstacleRefresh = 0;
  // Opacidad de cada conexión ("i-j"), para que aparezcan y desaparezcan con fundido.
  const edgeAlpha = new Map();
  const FADE_SECONDS = 0.6;

  function refreshObstacles() {
    obstacles = [];
    if (!mapText) return;

    const canvasRect = canvas.getBoundingClientRect();
    mapText.querySelectorAll('.tc-logo, h2, p').forEach((element) => {
      const rect = element.getBoundingClientRect();
      const padding = element.matches('.tc-logo') ? 12 : 9;
      obstacles.push({
        left: rect.left - canvasRect.left - padding,
        right: rect.right - canvasRect.left + padding,
        top: rect.top - canvasRect.top - padding,
        bottom: rect.bottom - canvasRect.top + padding
      });
    });
  }

  function pointIsBlocked(x, y) {
    return obstacles.some((rect) =>
      x >= rect.left && x <= rect.right &&
      y >= rect.top && y <= rect.bottom
    );
  }

  // Liang-Barsky: detecta si una conexión recta atravesaría el texto o la esfera.
  function segmentTouchesObstacle(x1, y1, x2, y2, rect) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const p = [-dx, dx, -dy, dy];
    const q = [
      x1 - rect.left,
      rect.right - x1,
      y1 - rect.top,
      rect.bottom - y1
    ];
    let start = 0;
    let end = 1;

    for (let i = 0; i < 4; i += 1) {
      if (Math.abs(p[i]) < 0.0001) {
        if (q[i] < 0) return false;
        continue;
      }

      const ratio = q[i] / p[i];
      if (p[i] < 0) start = Math.max(start, ratio);
      else end = Math.min(end, ratio);
      if (start > end) return false;
    }

    return true;
  }

  function connectionIsBlocked(a, b) {
    return obstacles.some((rect) =>
      segmentTouchesObstacle(a.x, a.y, b.x, b.y, rect)
    );
  }

  function createNode() {
    const margin = 8;
    let x = margin;
    let y = margin;

    for (let attempt = 0; attempt < 100; attempt += 1) {
      x = margin + Math.random() * Math.max(1, width - margin * 2);
      y = margin + Math.random() * Math.max(1, height - margin * 2);
      if (!pointIsBlocked(x, y)) break;
    }

    const angle = Math.random() * Math.PI * 2;
    const speed = 4 + Math.random() * 5;
    return {
      x,
      y,
      vx: Math.cos(angle) * speed,
      vy: Math.sin(angle) * speed,
      phase: Math.random() * Math.PI * 2,
      radius: 1.8 + Math.random() * 0.8,
      alpha: 0
    };
  }

  function resize() {
    const newWidth = canvas.clientWidth;
    const newHeight = canvas.clientHeight;
    // ResizeObserver y el evento resize disparan seguido aunque el tamaño no cambie.
    if (newWidth === width && newHeight === height) return;

    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const scaleX = width ? newWidth / width : 1;
    const scaleY = height ? newHeight / height : 1;
    width = newWidth;
    height = newHeight;
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

    refreshObstacles();
    const compact = compactViewport.matches;
    const targetCount = Math.min(
      52,
      Math.max(compact ? 14 : 28, Math.round((width * height) / (compact ? 6000 : 5000)))
    );

    // Conservar los nodos existentes (reubicados a escala) en vez de sortear una red nueva.
    nodes.forEach((node) => {
      node.x *= scaleX;
      node.y *= scaleY;
    });
    while (nodes.length < targetCount) nodes.push(createNode());
    if (nodes.length > targetCount) {
      nodes.length = targetCount;
      edgeAlpha.forEach((_, key) => {
        const [i, j] = key.split('-').map(Number);
        if (i >= targetCount || j >= targetCount) edgeAlpha.delete(key);
      });
    }

    backgroundDots = [[], [], []];
    const spacing = compact ? 13 : 15;
    for (let y = 9; y < height; y += spacing) {
      for (let x = 9; x < width; x += spacing) {
        if (Math.random() < 0.14) continue;
        const tone = Math.random();
        const group = tone < 0.48 ? 0 : tone < 0.82 ? 1 : 2;
        const radius = group === 0 ? 0.85 : group === 1 ? 1.05 : 1.25;
        backgroundDots[group].push({
          x: x + (Math.random() - 0.5) * 0.8,
          y: y + (Math.random() - 0.5) * 0.8,
          radius
        });
      }
    }

    lastFrame = 0;
  }

  function moveNodes(delta) {
    nodes.forEach((node) => {
      const nextX = node.x + node.vx * delta;
      const nextY = node.y + node.vy * delta;

      if (nextX < 7 || nextX > width - 7) node.vx *= -1;
      if (nextY < 7 || nextY > height - 7) node.vy *= -1;

      const x = Math.max(7, Math.min(width - 7, node.x + node.vx * delta));
      const y = Math.max(7, Math.min(height - 7, node.y + node.vy * delta));

      // Si el texto se acomodó encima de un nodo, dejarlo salir en vez de atraparlo.
      if (pointIsBlocked(x, y) && !pointIsBlocked(node.x, node.y)) {
        node.vx *= -1;
        node.vy *= -1;
        return;
      }

      node.x = x;
      node.y = y;
    });
  }

  function buildConnections(compact) {
    const reach = compact ? 104 : 126;
    const candidates = [];

    for (let i = 0; i < nodes.length; i += 1) {
      const a = nodes[i];
      if (pointIsBlocked(a.x, a.y)) continue;

      for (let j = i + 1; j < nodes.length; j += 1) {
        const b = nodes[j];
        const distance = Math.hypot(b.x - a.x, b.y - a.y);
        if (distance > reach || pointIsBlocked(b.x, b.y) || connectionIsBlocked(a, b)) continue;
        // Las conexiones visibles tienen prioridad para no reemplazarlas por otra apenas más corta.
        const rank = edgeAlpha.has(i + '-' + j) ? distance * 0.8 : distance;
        candidates.push({ i, j, distance, rank });
      }
    }

    candidates.sort((a, b) => a.rank - b.rank);
    const degree = new Uint8Array(nodes.length);
    const connections = [];
    const maxConnections = compact ? 3 : 4;

    candidates.forEach((edge) => {
      if (degree[edge.i] >= maxConnections || degree[edge.j] >= maxConnections) return;
      degree[edge.i] += 1;
      degree[edge.j] += 1;
      connections.push(edge);
    });

    return { connections, reach };
  }

  function drawBackgroundDots(compact) {
    const colors = compact
      ? [
          'rgba(58, 139, 116, 0.10)',
          'rgba(58, 139, 116, 0.16)',
          'rgba(58, 139, 116, 0.23)'
        ]
      : [
          'rgba(58, 139, 116, 0.08)',
          'rgba(58, 139, 116, 0.13)',
          'rgba(58, 139, 116, 0.19)'
        ];

    backgroundDots.forEach((group, index) => {
      if (!group.length) return;
      ctx.beginPath();
      group.forEach((dot) => {
        ctx.moveTo(dot.x + dot.radius, dot.y);
        ctx.arc(dot.x, dot.y, dot.radius, 0, Math.PI * 2);
      });
      ctx.fillStyle = colors[index];
      ctx.fill();
    });
  }

  function drawConnections(now, delta, compact, connections, reach) {
    const baseOpacity = compact ? 0.5 : 0.38;
    const step = delta / FADE_SECONDS;

    // Las conexiones activas suben de opacidad y las que se cortaron bajan, sin saltos.
    const active = new Set();
    connections.forEach((edge) => {
      const key = edge.i + '-' + edge.j;
      active.add(key);
      edgeAlpha.set(key, Math.min(1, (edgeAlpha.get(key) || 0) + step));
    });
    edgeAlpha.forEach((alpha, key) => {
      if (active.has(key)) return;
      const [i, j] = key.split('-').map(Number);
      // Si la conexión ahora cruza el texto, se apaga más rápido para no taparlo.
      const fall = connectionIsBlocked(nodes[i], nodes[j]) ? step * 4 : step;
      if (alpha - fall <= 0) edgeAlpha.delete(key);
      else edgeAlpha.set(key, alpha - fall);
    });

    edgeAlpha.forEach((alpha, key) => {
      const [i, j] = key.split('-').map(Number);
      const a = nodes[i];
      const b = nodes[j];
      const distance = Math.hypot(b.x - a.x, b.y - a.y);
      const strength = Math.max(0, 1 - distance / reach);
      // Se desvanecen al acercarse al alcance máximo en vez de cortarse de golpe.
      const edgeFade = Math.min(1, strength / 0.3);
      const eased = alpha * alpha * (3 - 2 * alpha);
      const visibility = eased * edgeFade * Math.min(a.alpha, b.alpha);
      if (visibility <= 0.001) return;
      const opacity = baseOpacity * (0.55 + strength * 0.6) * visibility;
      const edge = { i, j };

      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      ctx.lineTo(b.x, b.y);
      ctx.strokeStyle = 'rgba(58, 139, 116, ' + opacity.toFixed(3) + ')';
      ctx.lineWidth = compact ? 1.25 : 1.15;
      ctx.stroke();

      // Pulsos pequeños recorren algunas conexiones para darles movimiento.
      if ((edge.i * 7 + edge.j * 3) % 4 === 0) {
        const progress = (now / 2100 + edge.i * 0.17 + edge.j * 0.11) % 1;
        const x = a.x + (b.x - a.x) * progress;
        const y = a.y + (b.y - a.y) * progress;
        ctx.beginPath();
        ctx.arc(x, y, compact ? 1.8 : 1.6, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(37, 120, 99, ' + ((0.5 + strength * 0.25) * visibility).toFixed(3) + ')';
        ctx.fill();
      }
    });
  }

  function updateNodeAlpha(delta) {
    const step = delta / FADE_SECONDS;
    nodes.forEach((node) => {
      const target = pointIsBlocked(node.x, node.y) ? 0 : 1;
      node.alpha = target > node.alpha
        ? Math.min(target, node.alpha + step)
        : Math.max(target, node.alpha - step);
    });
  }

  function drawNodes(now, compact) {
    nodes.forEach((node) => {
      if (node.alpha <= 0.001) return;

      const pulse = (Math.sin(now / 850 + node.phase) + 1) / 2;

      ctx.beginPath();
      ctx.arc(node.x, node.y, node.radius + pulse * 0.45, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(48, 133, 109, ' + ((compact ? 0.8 : 0.72) * node.alpha).toFixed(3) + ')';
      ctx.fill();

      ctx.beginPath();
      ctx.arc(node.x, node.y, node.radius + 2 + pulse * 1.2, 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(57, 137, 114, ' + ((0.12 + pulse * 0.12) * node.alpha).toFixed(3) + ')';
      ctx.lineWidth = 0.8;
      ctx.stroke();
    });
  }

  function frame(now) {
    if (!width || !height) {
      requestAnimationFrame(frame);
      return;
    }

    if (now - lastObstacleRefresh > 300) {
      refreshObstacles();
      lastObstacleRefresh = now;
    }

    const compact = compactViewport.matches;
    const delta = lastFrame
      ? Math.min((now - lastFrame) / 1000, 0.04)
      : 0;
    lastFrame = now;

    moveNodes(delta);
    updateNodeAlpha(delta);
    ctx.clearRect(0, 0, width, height);
    drawBackgroundDots(compact);
    const graph = buildConnections(compact);
    drawConnections(now, delta, compact, graph.connections, graph.reach);
    drawNodes(now, compact);

    requestAnimationFrame(frame);
  }

  resize();
  window.addEventListener('resize', resize);
  if (typeof ResizeObserver !== 'undefined') {
    new ResizeObserver(resize).observe(map);
  }
  frame(performance.now());

  // Mostrar/ocultar contraseña.
  const eye = document.getElementById('tc-eye');
  const password = document.getElementById('tc-password');
  if (eye && password) {
    eye.addEventListener('click', () => {
      const show = password.type === 'password';
      password.type = show ? 'text' : 'password';
      eye.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      eye.classList.toggle('active', show);
      eye.innerHTML = show
        ? '<i class="fa-solid fa-eye-slash"></i>'
        : '<i class="fa-solid fa-eye"></i>';
    });
  }

  const googleLogin = document.getElementById('tc-google-login');
  const rememberInput = document.querySelector('.tc-form input[name="remember"]');
  if (googleLogin && rememberInput) {
    googleLogin.addEventListener('click', () => {
      const target = new URL(googleLogin.href, window.location.href);
      target.searchParams.set('remember', rememberInput.checked ? '1' : '0');
      googleLogin.href = target.toString();
    });
  }
})();
