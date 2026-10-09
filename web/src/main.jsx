/**
 * main.jsx — React app ka entry point.
 *
 * Do raaste hain, do alag log:
 *   /         citizen page   — apna gaon, apna risk, alert subscribe
 *   /officer  command center — 30 gaon ka pura dashboard, login ke peeche
 *
 * NOTE: StrictMode jaan-bujh ke ON hai. Development mein ye har effect ko do baar chalata
 * hai — jisse pata chalta hai ki cleanup sahi likha hai ya nahi. Hamare data hook mein
 * AbortController + clearInterval har effect mein hai, isliye double-run se koi duplicate
 * request ya leak nahi hoti. Production build mein ye double-run hota hi nahi.
 */

import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'

// Ek hi stylesheet dono pages chalati hai, isliye yahan import hoti hai, App mein nahi.
import './styles/global.css'

import App from './App.jsx'
import CitizenPage from './pages/CitizenPage.jsx'
import OfficerGate from './pages/OfficerGate.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<CitizenPage />} />
        <Route
          path="/officer"
          element={
            <OfficerGate>
              <App />
            </OfficerGate>
          }
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  </StrictMode>,
)
