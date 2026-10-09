/**
 * OfficerGate — dashboard ke aage ka darwaza.
 *
 * KYUN ye ab zaroori hai: SIH build mein koi auth nahi tha, aur wo theek tha kyunki wo
 * local prototype tha. Ye build asli internet pe EC2 pe chal raha hai. Alert bhejna,
 * relief ka status badalna aur map upload karna ab bearer token ke peeche hain, aur wo
 * token yahan se milta hai.
 */

import { useState } from 'react'
import { IconRipple, IconLock } from '@tabler/icons-react'
import { officerLogin, officerToken } from '../api/client'

export default function OfficerGate({ children }) {
  const [signedIn, setSignedIn] = useState(() => Boolean(officerToken.get()))
  const [email, setEmail] = useState('officer@jalrakshak.in')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  if (signedIn) return children

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)

    try {
      const res = await officerLogin(email, password)
      officerToken.set(res.token)
      setSignedIn(true)
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="gate">
      <form className="gate-card" onSubmit={submit}>
        <div className="logo gate-logo">
          <span className="mk">
            <IconRipple className="ti" />
          </span>
          JalRakshak
          <span className="tag">COMMAND CENTER</span>
        </div>

        <p className="gate-sub">
          District control room. Sign in to see the risk map and send alerts.
        </p>

        <label className="fld-lbl" htmlFor="gate-email">Officer email</label>
        <input
          id="gate-email"
          className="fld"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
          autoComplete="username"
        />

        <label className="fld-lbl" htmlFor="gate-pass">Password</label>
        <input
          id="gate-pass"
          className="fld"
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
          autoComplete="current-password"
        />

        <button className="btn-primary gate-btn" type="submit" disabled={busy}>
          <IconLock className="ti" />
          {busy ? 'Signing in...' : 'Sign in'}
        </button>

        {error && <div className="errbox gate-err">{error}</div>}

        <p className="gate-foot">
          Looking for flood warnings for your own area? <a href="/">Open the citizen page</a>.
        </p>
      </form>
    </div>
  )
}
