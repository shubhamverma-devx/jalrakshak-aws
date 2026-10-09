import { useCallback, useEffect, useRef, useState } from 'react'
import { api, officerToken } from '../api'
import RiskBadge from '../components/RiskBadge'
import ZoneMap from '../components/ZoneMap'

const LEVELS = ['Safe', 'Watch', 'Warning', 'Severe']

function LoginCard({ onSignedIn }) {
  const [email, setEmail] = useState('officer@jalrakshak.in')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      const res = await api.officerLogin(email, password)
      officerToken.set(res.token)
      onSignedIn()
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="page">
      <div className="login-shell card">
        <h2>District control room</h2>
        <p className="card-hint">Sign in to monitor zones and issue flood warnings.</p>

        <form onSubmit={submit}>
          <div className="field">
            <label className="label" htmlFor="officer-email">Officer email</label>
            <input id="officer-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </div>

          <div className="field">
            <label className="label" htmlFor="officer-password">Password</label>
            <input
              id="officer-password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>

          <div className="field">
            <button className="btn btn-block" type="submit" disabled={busy}>
              {busy ? 'Signing in...' : 'Sign in'}
            </button>
          </div>
        </form>

        {error && <div className="notice notice-error">{error}</div>}
      </div>
    </div>
  )
}

/**
 * Officer dashboard. Map plus table of every monitored zone, a trigger alert
 * button per zone that publishes to that zone's Amazon SNS topic, and an
 * inundation map upload per zone that lands in Amazon S3.
 */
export default function OfficerPage() {
  const [signedIn, setSignedIn] = useState(() => Boolean(officerToken.get()))
  const [zones, setZones] = useState([])
  const [summary, setSummary] = useState(null)
  const [alerts, setAlerts] = useState([])
  const [selected, setSelected] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busyAction, setBusyAction] = useState(null)
  const [flash, setFlash] = useState(null)
  const fileInput = useRef(null)
  // Which zone the file dialog was opened for. A ref, not state, so it cannot
  // be stale by the time the user finishes picking a file.
  const mapTarget = useRef(null)

  const load = useCallback(async () => {
    const [zoneRes, alertRes] = await Promise.all([api.zones(), api.alerts()])
    setZones(zoneRes.data)
    setSummary(zoneRes.summary)
    setAlerts(alertRes.data)
    setSelected((current) => current ?? [...zoneRes.data].sort((a, b) => b.risk_score - a.risk_score)[0]?.slug ?? null)
    setLoading(false)
  }, [])

  useEffect(() => {
    if (!signedIn) return
    load().catch((e) => setFlash({ kind: 'error', text: e.message }))
  }, [signedIn, load])

  function signOut() {
    officerToken.clear()
    setSignedIn(false)
  }

  async function guarded(key, work) {
    setBusyAction(key)
    setFlash(null)

    try {
      const result = await work()
      await load()
      if (result) setFlash(result)
    } catch (e) {
      if (e.status === 401) {
        signOut()
        setFlash({ kind: 'error', text: 'Session expired. Please sign in again.' })
      } else {
        setFlash({ kind: 'error', text: e.message })
      }
    } finally {
      setBusyAction(null)
    }
  }

  const triggerAlert = (zone) =>
    guarded(`alert:${zone.slug}`, async () => {
      const res = await api.triggerAlert(zone.slug, null)
      const sent = res.data.delivery_status === 'sent'

      return {
        kind: sent ? 'ok' : 'warn',
        text: `${zone.name}: ${res.data.risk_level} alert to ${res.data.recipients_count} subscriber(s). ${res.message}`,
      }
    })

  /** Adds a reading 40 mm wetter and 0.35 m higher, to show risk moving live. */
  const simulateRain = (zone) =>
    guarded(`rain:${zone.slug}`, async () => {
      const rainfall = Math.round(((zone.rainfall_mm ?? 0) + 40) * 10) / 10
      const water = Math.round(((zone.water_level_m ?? 0) + 0.35) * 100) / 100
      const res = await api.addReading(zone.slug, rainfall, water)

      return {
        kind: res.risk_changed ? 'warn' : 'info',
        text: res.risk_changed
          ? `${zone.name}: rainfall now ${rainfall} mm. Risk moved ${res.risk_before} to ${res.risk_after}.`
          : `${zone.name}: rainfall now ${rainfall} mm. Risk still ${res.risk_after}.`,
      }
    })

  function pickMap(zone) {
    mapTarget.current = zone.slug
    setSelected(zone.slug)
    fileInput.current?.click()
  }

  const uploadMap = (event) => {
    const file = event.target.files?.[0]
    event.target.value = ''

    const slug = mapTarget.current
    if (!file || !slug) return

    const zone = zones.find((z) => z.slug === slug)

    return guarded(`map:${slug}`, async () => {
      const res = await api.uploadMap(slug, file)
      return { kind: 'ok', text: `${zone?.name ?? slug}: ${res.message}` }
    })
  }

  if (!signedIn) return <LoginCard onSignedIn={() => { setLoading(true); setSignedIn(true) }} />

  if (loading) return <div className="page"><div className="card muted">Loading zones...</div></div>

  const selectedZone = zones.find((z) => z.slug === selected)

  return (
    <div className="page">
      <input
        ref={fileInput}
        type="file"
        accept=".png,.jpg,.jpeg,.webp,.json,.geojson"
        onChange={uploadMap}
        style={{ display: 'none' }}
      />

      <div className="spread" style={{ marginBottom: 16 }}>
        <div>
          <h1>Officer dashboard</h1>
          <p className="muted small" style={{ margin: '4px 0 0' }}>
            Assam flood monitoring. Updated {summary ? new Date(summary.updated_at).toLocaleTimeString('en-IN') : ''}.
          </p>
        </div>
        <div className="row-actions">
          <button className="btn btn-ghost btn-sm" onClick={() => load()}>Refresh</button>
          <button className="btn btn-ghost btn-sm" onClick={signOut}>Sign out</button>
        </div>
      </div>

      {flash && <div className={`notice notice-${flash.kind}`} style={{ marginBottom: 16 }}>{flash.text}</div>}

      <div className="stat-row">
        {LEVELS.map((level) => (
          <div className="metric" key={level}>
            <div className="metric-label">{level}</div>
            <div className="metric-value" style={{ color: `var(--${level.toLowerCase()})` }}>
              {summary?.by_level?.[level] ?? 0}
            </div>
            <div className="metric-note">zones</div>
          </div>
        ))}

        <div className="metric">
          <div className="metric-label">People at risk</div>
          <div className="metric-value">{(summary?.people_at_risk ?? 0).toLocaleString('en-IN')}</div>
          <div className="metric-note">Warning and Severe zones</div>
        </div>
      </div>

      <div className="dash-grid">
        <div className="card">
          <div className="card-title">Monitored zones</div>
          <div className="card-hint">Marker size and colour follow the computed risk. Click a zone to select it.</div>
          <div className="map-shell">
            <ZoneMap zones={zones} selectedSlug={selected} onSelect={setSelected} />
          </div>
        </div>

        <div>
          <div className="card">
            <div className="card-title">Zone status</div>
            <div className="card-hint">
              Risk is computed from rainfall and water level against each zone's own warning and
              danger marks.
            </div>

            <div className="table-wrap">
              <table className="zones">
                <thead>
                  <tr>
                    <th>Zone</th>
                    <th>Rain 24h</th>
                    <th>Water level</th>
                    <th>Risk</th>
                    <th>Subs</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {zones.map((zone) => (
                    <tr
                      key={zone.slug}
                      className={zone.slug === selected ? 'selected' : ''}
                      onClick={() => setSelected(zone.slug)}
                    >
                      <td>
                        <strong>{zone.name}</strong>
                        <div className="small muted">{zone.district}</div>
                      </td>
                      <td className="num">{zone.rainfall_mm ?? '-'} mm</td>
                      <td className="num">
                        <span className={zone.water_level_m >= zone.danger_level_m ? 'over' : ''}>
                          {zone.water_level_m ?? '-'} m
                        </span>
                        <div className="small muted">danger {zone.danger_level_m}</div>
                      </td>
                      <td><RiskBadge level={zone.risk_level} /></td>
                      <td className="num">{zone.subscribers_count}</td>
                      <td>
                        <div className="row-actions">
                          <button
                            className={`btn btn-sm ${zone.alertable ? 'btn-danger' : 'btn-ghost'}`}
                            disabled={busyAction !== null}
                            onClick={(e) => { e.stopPropagation(); triggerAlert(zone) }}
                            title="Publish a flood warning to this zone's Amazon SNS topic"
                          >
                            {busyAction === `alert:${zone.slug}` ? 'Sending...' : 'Trigger alert'}
                          </button>
                          <button
                            className="btn btn-ghost btn-sm"
                            disabled={busyAction !== null}
                            onClick={(e) => { e.stopPropagation(); pickMap(zone) }}
                            title="Upload an inundation map to Amazon S3"
                          >
                            {busyAction === `map:${zone.slug}` ? 'Uploading...' : 'Map'}
                          </button>
                          <button
                            className="btn btn-ghost btn-sm"
                            disabled={busyAction !== null}
                            onClick={(e) => { e.stopPropagation(); simulateRain(zone) }}
                            title="Add a wetter reading, to show the risk level move"
                          >
                            {busyAction === `rain:${zone.slug}` ? '...' : '+40mm'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {selectedZone && (
            <div className="card">
              <div className="spread" style={{ marginBottom: 4 }}>
                <div className="card-title">{selectedZone.name}</div>
                <RiskBadge level={selectedZone.risk_level} />
              </div>
              <ul className="reason-list">
                {selectedZone.reasons?.map((reason, i) => <li key={i}>{reason}</li>)}
              </ul>

              {selectedZone.inundation_map_url && (
                <div className="map-preview">
                  <img src={selectedZone.inundation_map_url} alt={`Inundation map for ${selectedZone.name}`} />
                </div>
              )}
            </div>
          )}

          <div className="card">
            <div className="spread" style={{ marginBottom: 4 }}>
              <div className="card-title">Alert log</div>
              <span className="aws-tag">Amazon SNS</span>
            </div>

            {alerts.length === 0 ? (
              <div className="card-hint" style={{ marginBottom: 0 }}>
                No alerts issued yet. Press Trigger alert on a Warning or Severe zone.
              </div>
            ) : (
              <ul className="alert-log">
                {alerts.slice(0, 8).map((alert) => (
                  <li key={alert.id}>
                    <div className="alert-log-head">
                      <RiskBadge level={alert.risk_level} />
                      <strong>{alert.zone}</strong>
                      <span className="muted small">
                        {new Date(alert.created_at).toLocaleString('en-IN')}
                      </span>
                    </div>
                    <div className="small muted" style={{ marginTop: 4 }}>
                      {alert.recipients_count} subscriber(s) / {alert.delivery_status}
                      {alert.delivery_note ? ` / ${alert.delivery_note}` : ''}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
