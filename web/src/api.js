// Thin fetch wrapper. Base path is relative, so the same build works on
// localhost through the Vite proxy and on EC2 behind Nginx.
const BASE = '/api'

const TOKEN_KEY = 'jalrakshak.officer.token'

export const officerToken = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (t) => localStorage.setItem(TOKEN_KEY, t),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

async function request(path, { method = 'GET', body, auth = false, isForm = false } = {}) {
  const headers = {}
  if (!isForm) headers['Content-Type'] = 'application/json'
  headers['Accept'] = 'application/json'
  if (auth) headers['Authorization'] = `Bearer ${officerToken.get() ?? ''}`

  const res = await fetch(`${BASE}${path}`, {
    method,
    headers,
    body: isForm ? body : body ? JSON.stringify(body) : undefined,
  })

  const text = await res.text()
  const data = text ? JSON.parse(text) : null

  if (!res.ok) {
    const err = new Error(data?.message || `Request failed (${res.status})`)
    err.status = res.status
    err.payload = data
    throw err
  }

  return data
}

export const api = {
  health: () => request('/health'),
  zones: () => request('/zones'),
  zone: (slug) => request(`/zones/${slug}`),
  alerts: () => request('/alerts'),
  subscribe: (payload) => request('/subscribe', { method: 'POST', body: payload }),

  officerLogin: (email, password) =>
    request('/officer/login', { method: 'POST', body: { email, password } }),

  addReading: (slug, rainfall_mm, water_level_m) =>
    request(`/officer/zones/${slug}/readings`, {
      method: 'POST',
      auth: true,
      body: { rainfall_mm, water_level_m },
    }),

  triggerAlert: (slug, note) =>
    request(`/officer/zones/${slug}/alert`, { method: 'POST', auth: true, body: { note } }),

  uploadMap: (slug, file) => {
    const form = new FormData()
    form.append('map', file)
    return request(`/officer/zones/${slug}/map`, { method: 'POST', auth: true, body: form, isForm: true })
  },
}
