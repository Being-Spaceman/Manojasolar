// Rotating earth hero animation (Three.js, loaded from CDN in index.html)
(function () {
  const canvas = document.getElementById('earth-canvas');
  if (!canvas || typeof THREE === 'undefined') return;

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
  camera.position.z = 6.2;

  const globeGroup = new THREE.Group();
  scene.add(globeGroup);

  // Thin green atmosphere rim — a forest-fringe halo around the whole silhouette
  const glowGeo = new THREE.SphereGeometry(2.05, 48, 48);
  const glowMat = new THREE.MeshBasicMaterial({ color: 0x39b54a, transparent: true, opacity: 0.16, side: THREE.BackSide });
  const glow = new THREE.Mesh(glowGeo, glowMat);
  globeGroup.add(glow);

  // Grid-cell texture for solar panels: bright white frame, navy cells, crisp white grid lines
  function makePanelGridTexture() {
    const c = document.createElement('canvas');
    c.width = 320; c.height = 200;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, c.width, c.height);
    const pad = 16;
    ctx.fillStyle = '#132540';
    ctx.fillRect(pad, pad, c.width - pad * 2, c.height - pad * 2);
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 3;
    const cols = 6, rows = 4;
    for (let i = 1; i < cols; i++) {
      const x = pad + ((c.width - pad * 2) / cols) * i;
      ctx.beginPath(); ctx.moveTo(x, pad); ctx.lineTo(x, c.height - pad); ctx.stroke();
    }
    for (let j = 1; j < rows; j++) {
      const y = pad + ((c.height - pad * 2) / rows) * j;
      ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(c.width - pad, y); ctx.stroke();
    }
    const tex = new THREE.CanvasTexture(c);
    tex.anisotropy = 8;
    return tex;
  }

  // Small tree-canopy cluster texture (a few overlapping green blobs, transparent background)
  function makeTreeTexture() {
    const c = document.createElement('canvas');
    c.width = 64; c.height = 64;
    const ctx = c.getContext('2d');
    const blobs = [
      { x: 32, y: 36, r: 20, color: '#1f7a34' },
      { x: 20, y: 30, r: 14, color: '#2c9944' },
      { x: 44, y: 30, r: 14, color: '#2c9944' },
      { x: 32, y: 22, r: 15, color: '#39b54a' },
    ];
    blobs.forEach((b) => {
      const grad = ctx.createRadialGradient(b.x, b.y, 1, b.x, b.y, b.r);
      grad.addColorStop(0, b.color);
      grad.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = grad;
      ctx.beginPath();
      ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2);
      ctx.fill();
    });
    return new THREE.CanvasTexture(c);
  }

  // Load the Earth texture as a raw image first so we can sample its pixels
  // (to tell land from ocean) before deciding where to scatter panels vs trees.
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = () => {
    const sampleCanvas = document.createElement('canvas');
    sampleCanvas.width = img.width;
    sampleCanvas.height = img.height;
    const sampleCtx = sampleCanvas.getContext('2d');
    sampleCtx.drawImage(img, 0, 0);
    let imgData = null;
    try {
      imgData = sampleCtx.getImageData(0, 0, sampleCanvas.width, sampleCanvas.height).data;
    } catch (e) {
      imgData = null; // CORS-tainted canvas fallback: treat everything as land
    }

    // THREE.Spherical's theta and SphereGeometry's internal UV theta are offset
    // by PI/2 (different parameterizations of the same sphere) — correct for
    // that here so pixel lookups line up with what's actually rendered.
    function isLand(phi, theta) {
      if (!imgData) return true;
      const u = ((((theta + Math.PI / 2) / (2 * Math.PI)) % 1) + 1) % 1;
      const v = phi / Math.PI;
      const x = Math.floor(u * sampleCanvas.width);
      const y = Math.floor(v * sampleCanvas.height);
      const idx = (y * sampleCanvas.width + x) * 4;
      const r = imgData[idx], g = imgData[idx + 1], b = imgData[idx + 2];
      // Ocean pixels in this texture are distinctly blue-dominant; land/sand/forest are not.
      return !(b > r + 12 && b > g + 4);
    }

    const earthTexture = new THREE.CanvasTexture(sampleCanvas);
    const globeGeo = new THREE.SphereGeometry(2, 64, 64);
    const globeMat = new THREE.MeshPhongMaterial({
      map: earthTexture,
      shininess: 6,
      specular: 0x11161c,
    });
    const globe = new THREE.Mesh(globeGeo, globeMat);
    globeGroup.add(globe);

    // Solar panel tiles — unlit material so they read clearly at every angle
    const panelTexture = makePanelGridTexture();
    const panelGroup = new THREE.Group();
    const panelGeo = new THREE.PlaneGeometry(0.48, 0.31);
    const panelMat = new THREE.MeshBasicMaterial({ map: panelTexture, side: THREE.DoubleSide });

    // Tree canopy clusters, scattered only on land points
    const treeTexture = makeTreeTexture();
    const treeGroup = new THREE.Group();
    const treeMat = new THREE.SpriteMaterial({ map: treeTexture, transparent: true, depthWrite: false });

    // Proper Fibonacci sphere point distribution — evenly spaced points with no
    // spiral/banding bias, unlike a naive sqrt(N)*phi spiral (which clusters
    // points along a few bands and was why panels bunched up over one patch
    // of ocean instead of spreading across the globe).
    function fibonacciSpherePoint(i, n) {
      const goldenAngle = Math.PI * (3 - Math.sqrt(5));
      const phi = Math.acos(1 - (2 * (i + 0.5)) / n);
      const theta = (goldenAngle * i) % (2 * Math.PI);
      return { phi, theta };
    }

    // Trees: dense pass, land points only.
    const treeSamples = 220;
    for (let i = 0; i < treeSamples; i++) {
      const { phi, theta } = fibonacciSpherePoint(i, treeSamples);
      if (!isLand(phi, theta)) continue;
      const sprite = new THREE.Sprite(treeMat);
      const scale = 0.24 + Math.random() * 0.13;
      sprite.scale.set(scale, scale, 1);
      sprite.position.setFromSphericalCoords(2.025, phi, theta);
      treeGroup.add(sprite);
    }

    // Panels: independent pass, ocean points only — separate sample set so
    // panel density/spread isn't coupled to (or starved by) the tree pass.
    const panelSamples = 46;
    for (let i = 0; i < panelSamples; i++) {
      const { phi, theta } = fibonacciSpherePoint(i, panelSamples);
      if (isLand(phi, theta)) continue;
      const panel = new THREE.Mesh(panelGeo, panelMat);
      panel.position.setFromSphericalCoords(2.06, phi, theta);
      panel.lookAt(0, 0, 0);
      panel.rotateZ((Math.random() - 0.5) * 0.5);
      panelGroup.add(panel);
    }

    globeGroup.add(treeGroup);
    globeGroup.add(panelGroup);

    // Latur, Maharashtra marker — a pulsing pin with a text label, placed at
    // its real coordinates using the same lat/long -> phi/theta conversion
    // derived (and verified) for the land/ocean sampling above.
    function latLonToPhiTheta(latDeg, lonDeg) {
      const lat = (latDeg * Math.PI) / 180;
      const lon = (lonDeg * Math.PI) / 180;
      const phi = Math.PI / 2 - lat;
      const theta = lon + Math.PI / 2;
      return { phi, theta };
    }

    function makePinLabelTexture(text) {
      const c = document.createElement('canvas');
      const scale = 4;
      c.width = 340 * scale; c.height = 90 * scale;
      const ctx = c.getContext('2d');
      ctx.scale(scale, scale);
      const w = 340, h = 90;
      const pad = 10;
      ctx.fillStyle = 'rgba(13, 35, 64, 0.92)';
      const r = 14;
      ctx.beginPath();
      ctx.moveTo(pad + r, pad);
      ctx.arcTo(w - pad, pad, w - pad, h - pad, r);
      ctx.arcTo(w - pad, h - pad, pad, h - pad, r);
      ctx.arcTo(pad, h - pad, pad, pad, r);
      ctx.arcTo(pad, pad, w - pad, pad, r);
      ctx.closePath();
      ctx.fill();
      ctx.strokeStyle = '#39b54a';
      ctx.lineWidth = 3;
      ctx.stroke();
      ctx.fillStyle = '#ffffff';
      ctx.font = '700 26px Poppins, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(text, w / 2, h / 2);
      return new THREE.CanvasTexture(c);
    }

    const laturPoint = latLonToPhiTheta(18.4088, 76.5604);
    const pinAnchor = new THREE.Vector3().setFromSphericalCoords(2.0, laturPoint.phi, laturPoint.theta);

    // Glowing pulse ring under the pin
    const ringTexture = (() => {
      const c = document.createElement('canvas');
      c.width = 128; c.height = 128;
      const ctx = c.getContext('2d');
      const grad = ctx.createRadialGradient(64, 64, 10, 64, 64, 60);
      grad.addColorStop(0, 'rgba(57,181,74,0.9)');
      grad.addColorStop(0.6, 'rgba(57,181,74,0.25)');
      grad.addColorStop(1, 'rgba(57,181,74,0)');
      ctx.fillStyle = grad;
      ctx.beginPath();
      ctx.arc(64, 64, 60, 0, Math.PI * 2);
      ctx.fill();
      return new THREE.CanvasTexture(c);
    })();
    const pulseMat = new THREE.SpriteMaterial({ map: ringTexture, transparent: true, depthWrite: false });
    const pulseSprite = new THREE.Sprite(pulseMat);
    pulseSprite.scale.set(0.5, 0.5, 1);
    pulseSprite.position.copy(pinAnchor);
    globeGroup.add(pulseSprite);

    // Solid center dot
    const dotMat = new THREE.SpriteMaterial({
      map: (() => {
        const c = document.createElement('canvas');
        c.width = 64; c.height = 64;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.beginPath(); ctx.arc(32, 32, 22, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#39b54a';
        ctx.beginPath(); ctx.arc(32, 32, 15, 0, Math.PI * 2); ctx.fill();
        return new THREE.CanvasTexture(c);
      })(),
      transparent: true,
      depthWrite: false,
    });
    const dotSprite = new THREE.Sprite(dotMat);
    dotSprite.scale.set(0.11, 0.11, 1);
    dotSprite.position.copy(pinAnchor);
    globeGroup.add(dotSprite);

    // Label
    const labelTexture = makePinLabelTexture('Latur, Maharashtra');
    const labelMat = new THREE.SpriteMaterial({ map: labelTexture, transparent: true, depthWrite: false });
    const labelSprite = new THREE.Sprite(labelMat);
    labelSprite.scale.set(0.85, 0.225, 1);
    const labelOffset = pinAnchor.clone().normalize().multiplyScalar(0.22);
    labelSprite.position.copy(pinAnchor).add(labelOffset).add(new THREE.Vector3(0, 0.16, 0));
    globeGroup.add(labelSprite);

    const reduceMotionForPulse = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduceMotionForPulse) {
      (function pulse() {
        const t = (Date.now() % 2000) / 2000;
        const s = 0.4 + t * 0.5;
        pulseSprite.scale.set(s, s, 1);
        pulseMat.opacity = 1 - t;
        requestAnimationFrame(pulse);
      })();
    }
  };
  img.onerror = () => {
    // CDN fetch failed: fall back to a plain green-tinted sphere so the hero never shows blank
    const globeGeo = new THREE.SphereGeometry(2, 64, 64);
    const globeMat = new THREE.MeshPhongMaterial({ color: 0x1c3a2a, shininess: 6 });
    globeGroup.add(new THREE.Mesh(globeGeo, globeMat));
  };
  img.src = 'https://cdn.jsdelivr.net/gh/mrdoob/three.js@r155/examples/textures/planets/earth_atmos_2048.jpg';

  const ambient = new THREE.AmbientLight(0xffffff, 0.42);
  scene.add(ambient);
  const key = new THREE.DirectionalLight(0xffffff, 1.05);
  key.position.set(4, 3, 5);
  scene.add(key);
  const fill = new THREE.DirectionalLight(0xffffff, 0.3);
  fill.position.set(-3, -1, 4);
  scene.add(fill);
  const rim = new THREE.DirectionalLight(0x39b54a, 0.3);
  rim.position.set(-4, -2, -3);
  scene.add(rim);
  // Camera-facing light so front-on surfaces (panels included) don't go dark
  const front = new THREE.DirectionalLight(0xffffff, 0.55);
  front.position.set(0, 0, 8);
  scene.add(front);

  globeGroup.rotation.x = 0.15;
  globeGroup.rotation.y = 3.38; // precisely centers India/Latur front-and-center on load

  function resize() {
    const size = canvas.clientWidth || canvas.parentElement.clientWidth;
    renderer.setSize(size, size, false);
    camera.aspect = 1;
    camera.updateProjectionMatrix();
  }
  window.addEventListener('resize', resize);
  resize();

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function animate() {
    requestAnimationFrame(animate);
    if (!reduceMotion) {
      globeGroup.rotation.y += 0.0028;
    }
    renderer.render(scene, camera);
  }
  animate();
})();
