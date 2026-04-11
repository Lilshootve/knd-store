# Mind Wars–style hologram material (workflows/legendary-preset.json + holo fragment en mw-hologram-layers).
#
# Uso:
#   Blender → Scripting → abrir este archivo → Run Script.
#   Asigna el material "MindWars_Hologram_Legendary" al objeto.
#   Nodo "Time (driver #frame/fps)": clic derecho → Add Driver → expresión: #frame / bpy.context.scene.render.fps
#
# Eevee: Bloom activado. Cycles: sube Strength de emisión o Glare en compositing.
# Compatible Blender 3.x / 4.x (no usa ShaderNodeGeometry).

import bpy

MAT_NAME = "MindWars_Hologram_Legendary"

PRESET = {
    "color_r": 3.0,
    "color_g": 0.61,
    "color_b": 0.43,
    "opacity": 0.6,
    "fresnel_power": 0.4,
    "inner_glow": 0.45,
    "rim_strength": 1.4,
    "pulse_speed": 1.75,
    "pulse_strength": 0.32,
    "scan_speed": 2.5,
    "scan_density": 20.0,
    "flicker_intensity": 0.04,
}


def _new_node(nt, bl_idname, x, y, **props):
    n = nt.nodes.new(bl_idname)
    n.location = (x, y)
    for k, v in props.items():
        if hasattr(n, k):
            setattr(n, k, v)
    return n


def _link(nt, a, sock_a, b, sock_b):
    nt.links.new(a.outputs[sock_a], b.inputs[sock_b])


def _mix_shader_fac_socket(mix_node):
    for key in ("Fac", "Factor"):
        if key in mix_node.inputs:
            return key
    return 0


def build_material():
    if MAT_NAME in bpy.data.materials:
        bpy.data.materials.remove(bpy.data.materials[MAT_NAME], do_unlink=True)

    mat = bpy.data.materials.new(MAT_NAME)
    mat.use_nodes = True
    mat.blend_method = "BLEND"
    if hasattr(mat, "shadow_method"):
        mat.shadow_method = "NONE"
    if hasattr(mat, "show_transparent_back"):
        mat.show_transparent_back = True

    nt = mat.node_tree
    for n in list(nt.nodes):
        nt.nodes.remove(n)

    P = PRESET
    x = 0
    dx = 200

    out = _new_node(nt, "ShaderNodeOutputMaterial", x + 22 * dx, 0)
    mix_sh = _new_node(nt, "ShaderNodeMixShader", x + 20 * dx, 0)
    fac_sock = _mix_shader_fac_socket(mix_sh)

    bsdf_tr = _new_node(nt, "ShaderNodeBsdfTransparent", x + 18 * dx, -220)
    emis = _new_node(nt, "ShaderNodeEmission", x + 18 * dx, 200)
    emis.inputs["Strength"].default_value = 1.0

    _link(nt, bsdf_tr, "BSDF", mix_sh, 1)
    _link(nt, emis, "Emission", mix_sh, 2)
    _link(nt, mix_sh, "Shader", out, "Surface")

    t_node = _new_node(nt, "ShaderNodeValue", x, 400)
    t_node.outputs[0].default_value = 0.0
    t_node.label = "Time (driver #frame/fps)"

    # Posición mundo (Y para scanlines): TexCoord → Object + Vector Transform.
    # Evita ShaderNodeGeometry (quitado en Blender 4+) y ShaderNodePosition (no siempre disponible).
    texco = _new_node(nt, "ShaderNodeTexCoord", x, -120)
    vtf = _new_node(nt, "ShaderNodeVectorTransform", x + dx, 0)
    vtf.convert_from = "OBJECT"
    vtf.convert_to = "WORLD"
    _link(nt, texco, "Object", vtf, "Vector")
    sep_w = _new_node(nt, "ShaderNodeSeparateXYZ", x + 2 * dx, 0)
    _link(nt, vtf, "Vector", sep_w, "Vector")

    sep_uv = _new_node(nt, "ShaderNodeSeparateXYZ", x + dx, -300)
    _link(nt, texco, "UV", sep_uv, "Vector")

    m_yd = _new_node(nt, "ShaderNodeMath", x + 3 * dx, 0, operation="MULTIPLY")
    m_yd.inputs[1].default_value = P["scan_density"]
    _link(nt, sep_w, "Y", m_yd, 0)

    m_ts = _new_node(nt, "ShaderNodeMath", x + 3 * dx, -120, operation="MULTIPLY")
    _link(nt, t_node, "Value", m_ts, 0)
    m_ts.inputs[1].default_value = P["scan_speed"]

    m_scan_arg = _new_node(nt, "ShaderNodeMath", x + 4 * dx, -40, operation="ADD")
    _link(nt, m_yd, "Value", m_scan_arg, 0)
    _link(nt, m_ts, "Value", m_scan_arg, 1)

    sin_scan = _new_node(nt, "ShaderNodeMath", x + 5 * dx, -40, operation="SINE")
    _link(nt, m_scan_arg, "Value", sin_scan, 0)

    m_scan01 = _new_node(nt, "ShaderNodeMath", x + 6 * dx, -40, operation="MULTIPLY")
    _link(nt, sin_scan, "Value", m_scan01, 0)
    m_scan01.inputs[1].default_value = 0.5
    m_scan_bias = _new_node(nt, "ShaderNodeMath", x + 7 * dx, -40, operation="ADD")
    _link(nt, m_scan01, "Value", m_scan_bias, 0)
    m_scan_bias.inputs[1].default_value = 0.5

    pow3 = _new_node(nt, "ShaderNodeMath", x + 8 * dx, -40, operation="POWER")
    _link(nt, m_scan_bias, "Value", pow3, 0)
    pow3.inputs[1].default_value = 3.0

    m_scan_mix = _new_node(nt, "ShaderNodeMath", x + 9 * dx, -40, operation="MULTIPLY")
    _link(nt, pow3, "Value", m_scan_mix, 0)
    m_scan_mix.inputs[1].default_value = 0.35
    scan_line = _new_node(nt, "ShaderNodeMath", x + 10 * dx, -40, operation="ADD")
    _link(nt, m_scan_mix, "Value", scan_line, 0)
    scan_line.inputs[1].default_value = 0.65

    lw = _new_node(nt, "ShaderNodeLayerWeight", x + 2 * dx, 200)
    lw.inputs["Blend"].default_value = 0.5
    one_m_f = _new_node(nt, "ShaderNodeMath", x + 3 * dx, 200, operation="SUBTRACT")
    one_m_f.inputs[0].default_value = 1.0
    _link(nt, lw, "Facing", one_m_f, 1)
    clamp_f = _new_node(nt, "ShaderNodeMath", x + 4 * dx, 200, operation="MAXIMUM")
    _link(nt, one_m_f, "Value", clamp_f, 0)
    clamp_f.inputs[1].default_value = 0.0
    fresnel = _new_node(nt, "ShaderNodeMath", x + 5 * dx, 200, operation="POWER")
    _link(nt, clamp_f, "Value", fresnel, 0)
    fresnel.inputs[1].default_value = P["fresnel_power"]

    inner = _new_node(nt, "ShaderNodeMath", x + 6 * dx, 260, operation="SUBTRACT")
    inner.inputs[0].default_value = 1.0
    _link(nt, fresnel, "Value", inner, 1)
    core = _new_node(nt, "ShaderNodeMath", x + 7 * dx, 260, operation="POWER")
    _link(nt, inner, "Value", core, 0)
    core.inputs[1].default_value = 3.5
    core_g = _new_node(nt, "ShaderNodeMath", x + 8 * dx, 260, operation="MULTIPLY")
    _link(nt, core, "Value", core_g, 0)
    core_g.inputs[1].default_value = P["inner_glow"]

    rim = _new_node(nt, "ShaderNodeMath", x + 7 * dx, 140, operation="POWER")
    _link(nt, fresnel, "Value", rim, 0)
    rim.inputs[1].default_value = 1.2
    rim_s = _new_node(nt, "ShaderNodeMath", x + 8 * dx, 140, operation="MULTIPLY")
    _link(nt, rim, "Value", rim_s, 0)
    rim_s.inputs[1].default_value = P["rim_strength"]

    m_tp = _new_node(nt, "ShaderNodeMath", x + 3 * dx, 420, operation="MULTIPLY")
    _link(nt, t_node, "Value", m_tp, 0)
    m_tp.inputs[1].default_value = P["pulse_speed"]
    pulse = _new_node(nt, "ShaderNodeMath", x + 4 * dx, 420, operation="SINE")
    _link(nt, m_tp, "Value", pulse, 0)
    pulse01 = _new_node(nt, "ShaderNodeMath", x + 5 * dx, 420, operation="MULTIPLY")
    _link(nt, pulse, "Value", pulse01, 0)
    pulse01.inputs[1].default_value = 0.5
    pulse01b = _new_node(nt, "ShaderNodeMath", x + 6 * dx, 420, operation="ADD")
    _link(nt, pulse01, "Value", pulse01b, 0)
    pulse01b.inputs[1].default_value = 0.5

    m_tp2 = _new_node(nt, "ShaderNodeMath", x + 4 * dx, 520, operation="MULTIPLY")
    _link(nt, t_node, "Value", m_tp2, 0)
    m_tp2.inputs[1].default_value = P["pulse_speed"] * 0.4
    m_y3 = _new_node(nt, "ShaderNodeMath", x + 4 * dx, 620, operation="MULTIPLY")
    _link(nt, sep_w, "Y", m_y3, 0)
    m_y3.inputs[1].default_value = 3.0
    p2a = _new_node(nt, "ShaderNodeMath", x + 5 * dx, 560, operation="ADD")
    _link(nt, m_tp2, "Value", p2a, 0)
    _link(nt, m_y3, "Value", p2a, 1)
    pulse2 = _new_node(nt, "ShaderNodeMath", x + 6 * dx, 560, operation="SINE")
    _link(nt, p2a, "Value", pulse2, 0)
    pulse201 = _new_node(nt, "ShaderNodeMath", x + 7 * dx, 560, operation="MULTIPLY")
    _link(nt, pulse2, "Value", pulse201, 0)
    pulse201.inputs[1].default_value = 0.5
    pulse201b = _new_node(nt, "ShaderNodeMath", x + 8 * dx, 560, operation="ADD")
    _link(nt, pulse201, "Value", pulse201b, 0)
    pulse201b.inputs[1].default_value = 0.5

    m_e1 = _new_node(nt, "ShaderNodeMath", x + 9 * dx, 420, operation="MULTIPLY")
    _link(nt, pulse01b, "Value", m_e1, 0)
    m_e1.inputs[1].default_value = 0.6
    m_e2 = _new_node(nt, "ShaderNodeMath", x + 9 * dx, 560, operation="MULTIPLY")
    _link(nt, pulse201b, "Value", m_e2, 0)
    m_e2.inputs[1].default_value = 0.4
    energy_mix = _new_node(nt, "ShaderNodeMath", x + 10 * dx, 480, operation="ADD")
    _link(nt, m_e1, "Value", energy_mix, 0)
    _link(nt, m_e2, "Value", energy_mix, 1)

    ps_term = _new_node(nt, "ShaderNodeMath", x + 11 * dx, 480, operation="MULTIPLY")
    _link(nt, energy_mix, "Value", ps_term, 0)
    ps_term.inputs[1].default_value = P["pulse_strength"]
    one_m_ps = _new_node(nt, "ShaderNodeMath", x + 11 * dx, 360, operation="SUBTRACT")
    one_m_ps.inputs[0].default_value = 1.0
    one_m_ps.inputs[1].default_value = P["pulse_strength"]
    pulse_fac = _new_node(nt, "ShaderNodeMath", x + 12 * dx, 420, operation="ADD")
    _link(nt, one_m_ps, "Value", pulse_fac, 0)
    _link(nt, ps_term, "Value", pulse_fac, 1)

    rim_core = _new_node(nt, "ShaderNodeMath", x + 9 * dx, 180, operation="ADD")
    _link(nt, rim_s, "Value", rim_core, 0)
    _link(nt, core_g, "Value", rim_core, 1)

    a1 = _new_node(nt, "ShaderNodeMath", x + 11 * dx, 100, operation="MULTIPLY")
    _link(nt, rim_core, "Value", a1, 0)
    _link(nt, scan_line, "Value", a1, 1)
    a2 = _new_node(nt, "ShaderNodeMath", x + 13 * dx, 100, operation="MULTIPLY")
    _link(nt, a1, "Value", a2, 0)
    _link(nt, pulse_fac, "Value", a2, 1)

    m_t173 = _new_node(nt, "ShaderNodeMath", x + 3 * dx, -280, operation="MULTIPLY")
    _link(nt, t_node, "Value", m_t173, 0)
    m_t173.inputs[1].default_value = 17.3
    m_uv100 = _new_node(nt, "ShaderNodeMath", x + 4 * dx, -360, operation="MULTIPLY")
    _link(nt, sep_uv, "Y", m_uv100, 0)
    m_uv100.inputs[1].default_value = 100.0
    fl_arg = _new_node(nt, "ShaderNodeMath", x + 5 * dx, -320, operation="ADD")
    _link(nt, m_t173, "Value", fl_arg, 0)
    _link(nt, m_uv100, "Value", fl_arg, 1)
    fl_sin = _new_node(nt, "ShaderNodeMath", x + 6 * dx, -320, operation="SINE")
    _link(nt, fl_arg, "Value", fl_sin, 0)
    fl_m = _new_node(nt, "ShaderNodeMath", x + 7 * dx, -320, operation="MULTIPLY")
    _link(nt, fl_sin, "Value", fl_m, 0)
    fl_m.inputs[1].default_value = P["flicker_intensity"]
    flicker_sub = _new_node(nt, "ShaderNodeMath", x + 8 * dx, -320, operation="SUBTRACT")
    flicker_sub.inputs[0].default_value = 1.0
    _link(nt, fl_m, "Value", flicker_sub, 1)
    fl_add = _new_node(nt, "ShaderNodeMath", x + 9 * dx, -320, operation="ADD")
    _link(nt, flicker_sub, "Value", fl_add, 0)
    fl_add.inputs[1].default_value = 1.0 - P["flicker_intensity"]

    alpha_pre = _new_node(nt, "ShaderNodeMath", x + 14 * dx, 0, operation="MULTIPLY")
    _link(nt, a2, "Value", alpha_pre, 0)
    _link(nt, fl_add, "Value", alpha_pre, 1)

    alpha_final = _new_node(nt, "ShaderNodeMath", x + 16 * dx, 0, operation="MULTIPLY")
    _link(nt, alpha_pre, "Value", alpha_final, 0)
    alpha_final.inputs[1].default_value = P["opacity"]

    _link(nt, alpha_final, "Value", mix_sh, fac_sock)

    rim_c = _new_node(nt, "ShaderNodeMath", x + 10 * dx, 40, operation="MULTIPLY")
    _link(nt, rim_s, "Value", rim_c, 0)
    rim_c.inputs[1].default_value = 0.4
    core_c = _new_node(nt, "ShaderNodeMath", x + 10 * dx, -40, operation="MULTIPLY")
    _link(nt, core_g, "Value", core_c, 0)
    core_c.inputs[1].default_value = 0.5
    sum_c = _new_node(nt, "ShaderNodeMath", x + 11 * dx, 0, operation="ADD")
    sum_c.inputs[0].default_value = 0.9
    _link(nt, rim_c, "Value", sum_c, 1)
    sum_c2 = _new_node(nt, "ShaderNodeMath", x + 12 * dx, 0, operation="ADD")
    _link(nt, sum_c, "Value", sum_c2, 0)
    _link(nt, core_c, "Value", sum_c2, 1)

    rgb = _new_node(nt, "ShaderNodeRGB", x + 13 * dx, 200)
    rgb.outputs[0].default_value = (P["color_r"], P["color_g"], P["color_b"], 1.0)

    col_mul = _new_node(nt, "ShaderNodeVectorMath", x + 14 * dx, 120, operation="SCALE")
    _link(nt, rgb, "Color", col_mul, "Vector")
    _link(nt, sum_c2, "Value", col_mul, "Scale")
    _link(nt, col_mul, "Vector", emis, "Color")

    return mat


if __name__ == "__main__":
    m = build_material()
    print("Material creado:", m.name)
