# Vercel deployment

Root `frontend`; build `npm run build`; output `dist`; domain `app.whatstheup.in`; `VITE_API_BASE_URL=https://api.whatstheup.in/api/v1`. The SPA rewrite supports direct React Router visits. Use a separate API and explicit allowlisted origins for previews. Never expose secrets through `VITE_*`.
