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
        # Tout passe en blanc, SAUF les pixels très sombres (le noir du clavier)
        # que l'on conserve pour garder le détail des touches.
        if max(r, g, b) >= 70:
            px[x, y] = (255, 255, 255, a)
src.save("assets/logo-minitel-gpt-white.png")
print("Généré : assets/logo-minitel-gpt-white.png")
