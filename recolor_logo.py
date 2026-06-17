#!/usr/bin/env python3
"""Passe les pixels verts/turquoise du logo en blanc (garde crème, noir, alpha)."""
from PIL import Image

src = Image.open("assets/logo-minitel-gpt.png").convert("RGBA")
px = src.load()
w, h = src.size
for y in range(h):
    for x in range(w):
        r, g, b, a = px[x, y]
        if a == 0:
            continue
        mx, mn = max(r, g, b), min(r, g, b)
        # Vert/turquoise = G dominant + couleur saturée
        if g > r + 12 and (mx - mn) > 22:
            px[x, y] = (255, 255, 255, a)
src.save("assets/logo-minitel-gpt-white.png")
print("Généré : assets/logo-minitel-gpt-white.png")
