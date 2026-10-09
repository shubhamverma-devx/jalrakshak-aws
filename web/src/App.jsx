import { NavLink, Route, Routes } from 'react-router-dom'
import CitizenPage from './pages/CitizenPage'
import OfficerPage from './pages/OfficerPage'

export default function App() {
  return (
    <>
      <header className="site-header">
        <div className="site-header-inner">
          <div className="brand">
            <span className="brand-mark" aria-hidden="true">🌊</span>
            <div>
              <div className="brand-name">JalRakshak</div>
              <div className="brand-sub">Flood early warning for Assam</div>
            </div>
          </div>

          <nav className="header-nav">
            <NavLink to="/" end>Citizen</NavLink>
            <NavLink to="/officer">Officer</NavLink>
          </nav>
        </div>
      </header>

      <main>
        <Routes>
          <Route path="/" element={<CitizenPage />} />
          <Route path="/officer" element={<OfficerPage />} />
          <Route path="*" element={<CitizenPage />} />
        </Routes>
      </main>

      <footer className="site-footer">
        JalRakshak runs on AWS: Amazon EC2 hosts the app, Amazon S3 stores the inundation maps,
        Amazon SNS delivers the alerts.
      </footer>
    </>
  )
}
