"use client";

import { Suspense, useMemo, useRef } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { Sparkles } from "@react-three/drei";
import * as THREE from "three";

/**
 * Interactive light field. A grid of points reacts to the pointer — a movable
 * light source the visitor steers with the mouse to feel depth.
 */
function PointField() {
  const ref = useRef<THREE.Points>(null);
  const { pointer } = useThree();

  const { positions, count } = useMemo(() => {
    const c = 42;
    const arr = new Float32Array(c * c * 3);
    let i = 0;
    for (let x = 0; x < c; x++) {
      for (let z = 0; z < c; z++) {
        arr[i++] = (x - c / 2) * 0.42;
        arr[i++] = 0;
        arr[i++] = (z - c / 2) * 0.42;
      }
    }
    return { positions: arr, count: c * c };
  }, []);

  useFrame((state) => {
    if (!ref.current) return;
    const t = state.clock.elapsedTime;
    const geo = ref.current.geometry;
    const pos = geo.attributes.position as THREE.BufferAttribute;
    const lx = pointer.x * 8;
    const lz = -pointer.y * 8;
    for (let i = 0; i < count; i++) {
      const x = pos.getX(i);
      const z = pos.getZ(i);
      const wave = Math.sin(x * 0.6 + t) * Math.cos(z * 0.6 + t) * 0.35;
      const d = Math.hypot(x - lx, z - lz);
      const lift = Math.max(0, 1.4 - d * 0.25);
      pos.setY(i, wave + lift);
    }
    pos.needsUpdate = true;
    ref.current.rotation.y = t * 0.04;
  });

  return (
    <points ref={ref}>
      <bufferGeometry>
        <bufferAttribute
          attach="attributes-position"
          array={positions}
          count={count}
          itemSize={3}
        />
      </bufferGeometry>
      <pointsMaterial
        color="#e8cd7e"
        size={0.045}
        sizeAttenuation
        transparent
        opacity={0.9}
      />
    </points>
  );
}

export default function InnovationScene() {
  return (
    <Canvas
      dpr={[1, 1.8]}
      camera={{ position: [0, 5.5, 9], fov: 45 }}
      gl={{ antialias: true, alpha: true }}
      style={{ width: "100%", height: "100%" }}
    >
      <Suspense fallback={null}>
        <fog attach="fog" args={["#0a0a0b", 10, 22]} />
        <ambientLight intensity={0.4} />
        <PointField />
        <Sparkles count={40} scale={[14, 4, 14]} size={2} speed={0.2} color="#c9a227" />
      </Suspense>
    </Canvas>
  );
}
