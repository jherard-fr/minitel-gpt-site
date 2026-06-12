# Site vitrine — MINITEL GPT

Site web de présentation du projet [MINITEL GPT](https://github.com/jherard-fr/minitel-gpt) :
un Minitel Telic/Alcatel transformé en terminal de chat IA via un Raspberry Pi Zero 2 W.

🌐 **En ligne** : https://jherard-fr.github.io/minitel-gpt-site/

## Structure

```
index.html   — page unique (one-page)
style.css    — thème sombre + turquoise (couleurs du logo)
assets/      — logo, favicons, et VOS images
```

## Ajouter vos images

1. Déposez vos photos / captures dans `assets/` (ex. `assets/ecran-accueil.jpg`).
2. Dans `index.html`, repérez les blocs `<div class="placeholder">…</div>`.
3. Remplacez le bloc par une image :

```html
<img src="assets/ecran-accueil.jpg" alt="Écran d'accueil" class="shot">
```

(ajoutez au besoin une classe `.shot{width:100%;border-radius:12px;border:1px solid var(--border)}` dans `style.css`)

## Sections à enrichir

- **Captures d'écran** : 4 emplacements prévus
- **Électronique & câblage** : schéma/photo + section « pièges rencontrés » à compléter
- Le tableau de câblage et la stack technique sont déjà renseignés

## Déploiement

Hébergé via **GitHub Pages** (branche `main`, racine). Toute modification poussée
est publiée automatiquement en ~1 minute.
