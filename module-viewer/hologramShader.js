/**
 * @param {import('three').Texture | null | undefined} map
 * @param {Record<string, number>} preset
 */
export function createHologramMaterial(preset, options = {}) {
  const THREE = window.THREE;
  const sourceMap = options.map ?? null;
  const hasMap = !!sourceMap;

  if (!createHologramMaterial._whiteTex) {
    const data = new Uint8Array([255, 255, 255, 255]);
    const t = new THREE.DataTexture(data, 1, 1, THREE.RGBAFormat);
    t.needsUpdate = true;
    createHologramMaterial._whiteTex = t;
  }

  const mapUniform = hasMap ? sourceMap : createHologramMaterial._whiteTex;

  const blendIndex = Math.min(Math.max(Math.round(Number(preset.blendMode) || 0), 0), 2);
  const blendings = [
    THREE.NormalBlending,
    THREE.AdditiveBlending,
    THREE.MultiplyBlending
  ];
  const sideIndex = Math.min(Math.max(Math.round(Number(preset.materialSide) || 2), 0), 2);
  const sides = [THREE.FrontSide, THREE.BackSide, THREE.DoubleSide];

  const defines = {};
  if (hasMap) defines.USE_MAP = '1';

  return new THREE.ShaderMaterial({
    defines,
    transparent: true,
    depthWrite: false,
    depthTest: (preset.depthTest ?? 1) > 0.5,
    blending: blendings[blendIndex],
    side: sides[sideIndex],

    uniforms: {
      time: { value: 0 },
      map: { value: mapUniform },
      texOpacity: { value: preset.texOpacity ?? 0.38 },
      texAlphaInRgb: { value: preset.texAlphaInRgb ?? 0 },
      baseOpacity: { value: preset.baseOpacity ?? 0.12 },
      baseColor: {
        value: new THREE.Color(
          preset.baseR ?? 0,
          preset.baseG ?? 0.2,
          preset.baseB ?? 0.2
        )
      },
      color: {
        value: new THREE.Color(preset.colorR, preset.colorG, preset.colorB)
      },
      opacity: { value: preset.opacity },
      holoOpacityMul: { value: preset.holoOpacityMul ?? 1 },
      fresnelStrength: { value: preset.fresnelStrength },
      fresnelDotBias: { value: preset.fresnelDotBias ?? 0 },
      innerGlow: { value: preset.innerGlow ?? 0.2 },
      rimStrength: { value: preset.rimStrength ?? 0.9 },
      innerCorePower: { value: preset.innerCorePower ?? 3.5 },
      rimPower: { value: preset.rimPower ?? 1.2 },
      pulseSpeed: { value: preset.pulseSpeed },
      pulseStrength: { value: preset.pulseStrength },
      pulseMix: { value: preset.pulseMix ?? 0.4 },
      pulseSpatialFreq: { value: preset.pulseSpatialFreq ?? 3 },
      pulse2SpeedMul: { value: preset.pulse2SpeedMul ?? 0.4 },
      scanSpeed: { value: preset.scanSpeed },
      scanDensity: { value: preset.scanDensity },
      scanLinePower: { value: preset.scanLinePower ?? 3 },
      scanLineAmp: { value: preset.scanLineAmp ?? 0.35 },
      scanLineFloor: { value: preset.scanLineFloor ?? 0.65 },
      flicker: { value: preset.flickerIntensity },
      flickerSpeed: { value: preset.flickerSpeed ?? 17.3 },
      flickerSpatial: { value: preset.flickerSpatial ?? 10 },
      holoTintBase: { value: preset.holoTintBase ?? 0.9 },
      holoRimMul: { value: preset.holoRimMul ?? 0.4 },
      holoCoreMul: { value: preset.holoCoreMul ?? 0.5 },
      holoRgbClamp: { value: preset.holoRgbClamp ?? 3 }
    },

    vertexShader: `
      #include <common>
      #include <skinning_pars_vertex>
      varying vec3 vNormal;
      varying vec3 vViewDir;
      varying vec3 vWorldPos;
      #ifdef USE_MAP
      varying vec2 vUv;
      #endif

      void main() {
        #ifdef USE_MAP
        vUv = uv;
        #endif
        #include <beginnormal_vertex>
        #include <skinbase_vertex>
        #include <skinnormal_vertex>
        #include <defaultnormal_vertex>
        vNormal = normalize(transformedNormal);
        #include <begin_vertex>
        #include <skinning_vertex>
        vec4 worldPos = modelMatrix * vec4(transformed, 1.0);
        vWorldPos = worldPos.xyz;
        vec4 mvPos = modelViewMatrix * vec4(transformed, 1.0);
        vViewDir = normalize(-mvPos.xyz);
        #include <project_vertex>
      }
    `,

    fragmentShader: `
      uniform float time;
      uniform sampler2D map;
      uniform float texOpacity;
      uniform float texAlphaInRgb;
      uniform float baseOpacity;
      uniform vec3 baseColor;
      uniform vec3 color;
      uniform float opacity;
      uniform float holoOpacityMul;
      uniform float fresnelStrength;
      uniform float fresnelDotBias;
      uniform float innerGlow;
      uniform float rimStrength;
      uniform float innerCorePower;
      uniform float rimPower;
      uniform float pulseSpeed;
      uniform float pulseStrength;
      uniform float pulseMix;
      uniform float pulseSpatialFreq;
      uniform float pulse2SpeedMul;
      uniform float scanSpeed;
      uniform float scanDensity;
      uniform float scanLinePower;
      uniform float scanLineAmp;
      uniform float scanLineFloor;
      uniform float flicker;
      uniform float flickerSpeed;
      uniform float flickerSpatial;
      uniform float holoTintBase;
      uniform float holoRimMul;
      uniform float holoCoreMul;
      uniform float holoRgbClamp;

      varying vec3 vNormal;
      varying vec3 vViewDir;
      varying vec3 vWorldPos;
      #ifdef USE_MAP
      varying vec2 vUv;
      #endif

      void main() {
        float nd = max(dot(vNormal, vViewDir), fresnelDotBias);
        float fresnel = 1.0 - nd;
        fresnel = pow(fresnel, fresnelStrength);

        float scanLine = sin(vWorldPos.y * scanDensity + time * scanSpeed) * 0.5 + 0.5;
        scanLine = pow(max(scanLine, 0.0001), scanLinePower) * scanLineAmp + scanLineFloor;

        float pulse = sin(time * pulseSpeed) * 0.5 + 0.5;
        float pulse2 = sin(time * pulseSpeed * pulse2SpeedMul + vWorldPos.y * pulseSpatialFreq) * 0.5 + 0.5;
        float energy = mix(pulse, pulse2, pulseMix);

        float inner = 1.0 - fresnel;
        float core = pow(max(inner, 0.0001), innerCorePower) * innerGlow;

        float rim = pow(max(fresnel, 0.0001), rimPower) * rimStrength;

        float holoAlpha = (rim + core) * scanLine * (1.0 - pulseStrength + energy * pulseStrength);
        holoAlpha = clamp(holoAlpha, 0.0, 1.0);

        float flickM = 1.0 - flicker + flicker * sin(time * flickerSpeed + dot(vWorldPos, vec3(12.9898, 78.233, 45.164)) * flickerSpatial);
        holoAlpha *= flickM;

        vec3 holoRgb = color * (holoTintBase + rim * holoRimMul + core * holoCoreMul);
        holoRgb = clamp(holoRgb, 0.0, holoRgbClamp);
        holoAlpha *= opacity * holoOpacityMul;

        #ifdef USE_MAP
        vec4 texel = texture2D(map, vUv);
        float aMix = mix(1.0, texel.a, texAlphaInRgb);
        vec3 texRgb = texel.rgb * texOpacity * aMix;
        vec3 baseRgb = baseColor * baseOpacity;
        vec3 rgb = baseRgb + texRgb + holoRgb * holoAlpha;
        float outA = min(1.0, baseOpacity + texOpacity * texel.a + holoAlpha);
        gl_FragColor = vec4(rgb, outA);
        #else
        gl_FragColor = vec4(holoRgb, holoAlpha);
        #endif
      }
    `
  });
}
