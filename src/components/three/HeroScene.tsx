"use client";

import { Suspense, useMemo, useRef } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { Environment, Float, Sparkles } from "@react-three/drei";
import * as THREE from "three";

/** Slowly turning faceted monolith with a polished gold material. */
function Monolith() {
  const mesh = useRef<THREE.Mesh>(null);

  useFrame((state, delta) => {
    if (!mesh.current) return;
    mesh.current.rotation.y += delta * 0.18;
    mesh.current.rotation.x = Math.sin(state.clock.elapsedTime * 0.25) * 0.12;
  });

  return (
    <Float speed={1.2} rotationIntensity={0.25} floatIntensity={0.6}>
      <mesh ref={mesh} castShadow>
        <icosahedronGeometry args={[1.6, 0]} />
        <meshStandardMaterial
          color="#c9a227"
          metalness={1}
          roughness={0.18}
          envMapIntensity={1.4}
        />
      </mesh>
    </Float>
  );
}

/** Subtle parallax: the camera drifts toward the pointer. */
function CameraRig() {
  const { camera, pointer } = useThree();
  useFrame(() => {
    camera.position.x += (pointer.x * 1.2 - camera.position.x) * 0.04;
    camera.position.y += (pointer.y * 0.8 - camera.position.y) * 0.04;
    camera.lookAt(0, 0, 0);
  });
  return null;
}

function Rings() {
  const group = useRef<THREE.Group>(null);
  useFrame((state) => {
    if (group.current) {
      group.current.rotation.z = state.clock.elapsedTime * 0.06;
    }
  });
  const rings = useMemo(() => [2.6, 3.2, 3.9], []);
  return (
    <group ref={group}>
      {rings.map((r, i) => (
        <mesh key={r} rotation={[Math.PI / 2.2, 0, i * 0.4]}>
          <torusGeometry args={[r, 0.006, 16, 120]} />
          <meshBasicMaterial color="#e8cd7e" transparent opacity={0.35 - i * 0.08} />
        </mesh>
      ))}
    </group>
  );
}

export default function HeroScene() {
  return (
    <Canvas
      dpr={[1, 1.8]}
      camera={{ position: [0, 0, 6], fov: 42 }}
      gl={{ antialias: true, alpha: true }}
      style={{ width: "100%", height: "100%" }}
    >
      <Suspense fallback={null}>
        <ambientLight intensity={0.25} />
        <spotLight
          position={[5, 6, 5]}
          angle={0.4}
          penumbra={1}
          intensity={2.4}
          color="#fff2cf"
        />
        <pointLight position={[-5, -3, -4]} intensity={1.2} color="#c9a227" />
        <Monolith />
        <Rings />
        <Sparkles
          count={70}
          scale={[10, 6, 6]}
          size={2.4}
          speed={0.3}
          opacity={0.5}
          color="#e8cd7e"
        />
        <Environment preset="warehouse" />
        <CameraRig />
      </Suspense>
    </Canvas>
  );
}
