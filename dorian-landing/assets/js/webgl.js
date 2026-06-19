/* Dorian — subtle GPU caustics/gold-dust layer (lightweight raw WebGL).
   Renders flowing fractal-noise gold light onto canvas[data-webgl].
   Desktop only; bails gracefully without WebGL or with reduced motion. */
window.Dorian = window.Dorian || {};

window.Dorian.initWebGL = function () {
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var mobile = window.matchMedia("(max-width: 900px)").matches;
  if (reduced || mobile) return;

  var canvases = document.querySelectorAll("canvas[data-webgl]");
  if (!canvases.length) return;

  var VERT =
    "attribute vec2 p;void main(){gl_Position=vec4(p,0.0,1.0);}";
  var FRAG = [
    "precision mediump float;",
    "uniform float u_time;uniform vec2 u_res;",
    "float hash(vec2 p){return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453);}",
    "float noise(vec2 p){vec2 i=floor(p);vec2 f=fract(p);",
    "float a=hash(i),b=hash(i+vec2(1.0,0.0)),c=hash(i+vec2(0.0,1.0)),d=hash(i+vec2(1.0,1.0));",
    "vec2 u=f*f*(3.0-2.0*f);",
    "return mix(a,b,u.x)+(c-a)*u.y*(1.0-u.x)+(d-b)*u.x*u.y;}",
    "float fbm(vec2 p){float v=0.0,a=0.5;for(int i=0;i<5;i++){v+=a*noise(p);p*=2.0;a*=0.5;}return v;}",
    "void main(){",
    "vec2 uv=gl_FragCoord.xy/u_res.xy;",
    "vec2 p=uv*vec2(3.0,2.2);",
    "float t=u_time*0.05;",
    "float n=fbm(p+vec2(t,t*0.5)+fbm(p*1.5-t));",
    "float caustic=pow(n,2.6);",
    "vec3 gold=mix(vec3(0.03,0.05,0.09),vec3(0.82,0.67,0.28),caustic);",
    "gl_FragColor=vec4(gold,caustic*0.85);}",
  ].join("\n");

  canvases.forEach(function (canvas) {
    var gl =
      canvas.getContext("webgl", { alpha: true, antialias: false }) ||
      canvas.getContext("experimental-webgl");
    if (!gl) return;

    function compile(type, src) {
      var s = gl.createShader(type);
      gl.shaderSource(s, src);
      gl.compileShader(s);
      if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) return null;
      return s;
    }
    var vs = compile(gl.VERTEX_SHADER, VERT);
    var fs = compile(gl.FRAGMENT_SHADER, FRAG);
    if (!vs || !fs) return;
    var prog = gl.createProgram();
    gl.attachShader(prog, vs);
    gl.attachShader(prog, fs);
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) return;
    gl.useProgram(prog);

    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(
      gl.ARRAY_BUFFER,
      new Float32Array([-1, -1, 3, -1, -1, 3]),
      gl.STATIC_DRAW
    );
    var loc = gl.getAttribLocation(prog, "p");
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

    var uTime = gl.getUniformLocation(prog, "u_time");
    var uRes = gl.getUniformLocation(prog, "u_res");

    function resize() {
      var r = canvas.getBoundingClientRect();
      var w = Math.max(1, Math.floor(r.width * 0.5)); // half-res for speed
      var h = Math.max(1, Math.floor(r.height * 0.5));
      if (canvas.width !== w || canvas.height !== h) {
        canvas.width = w;
        canvas.height = h;
        gl.viewport(0, 0, w, h);
      }
      gl.uniform2f(uRes, w, h);
    }

    var running = true;
    var visible = true;
    // Pause when the section is off-screen.
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function (e) {
        visible = e[0].isIntersecting;
      }).observe(canvas);
    }

    var last = 0;
    var start = performance.now();
    function frame(now) {
      if (!running) return;
      requestAnimationFrame(frame);
      if (!visible) return;
      if (now - last < 33) return; // ~30fps
      last = now;
      resize();
      gl.uniform1f(uTime, (now - start) / 1000);
      gl.drawArrays(gl.TRIANGLES, 0, 3);
    }
    requestAnimationFrame(frame);
  });
};
