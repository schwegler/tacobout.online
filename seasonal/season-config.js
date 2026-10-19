/* Add reviewed seasons and their date windows here; no controller rewrite required. */
window.SchwegSeasonalConfig = {
  "schemaVersion": 2,
  "invitationDelayMs": 1000,
  "profile": {
    "id": "telly",
    "character": "Telly",
    "placement": "main"
  },
  "seasons": [
    {
      "id": "halloween-2026",
      "theme": "halloween",
      "label": "Halloween",
      "enabled": true,
      "window": {
        "from": "2026-10-01",
        "until": "2026-11-01"
      },
      "asset": "mascot.png",
      "kicker": "HALLOWEEN / 2026",
      "title": "Welcome to the fright show.",
      "detail": "Pumpkin-orange headlines, late-night ghosts and Telly on the vampire mic.",
      "prompt": "Care for a frightfully good show?"
    }
  ]
};
