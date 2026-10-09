import { useEffect, useMemo, useState } from 'react'
import { api } from '../api'
import RiskBadge from '../components/RiskBadge'
import ZoneMap from '../components/ZoneMap'

/**
 * Citizen side. Pick your area, see the risk level, read the inundation map,
 * subscribe to alerts. Built mobile first: one column, large type, no jargon.
 */
export default function CitizenPage() {
  const [zones, setZones] = useState([])
  const [slug, setSlug] = useState('')
  const [detail, setDetail] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [subscribing, setSubscribing] = useState(false)
  const [subResult, setSubResult] = useState(null)

  useEffect(() => {
    api
      .zones()
      .then((res) => {
        setZones(res.data)
        // Open on the zone that needs attention most, so the page is never dull.
        const worst = [...res.data].sort((a, b) => b.risk_score - a.risk_score)[0]
        setSlug((current) => current || worst?.slug || '')
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    if (!slug) return
    setSubResult(null)
    api.zone(slug).then((res) => setDetail(res.data)).catch((e) => setError(e.message))
  }, [slug])

  const selected = detail && detail.slug === slug ? detail : zones.find((z) => z.slug === slug)

  const trend = useMemo(() => {
    if (!detail?.readings?.length) return null
    const first = detail.readings[0]
    const last = detail.readings[detail.readings.length - 1]
    return { rain: last.rainfall_mm - first.rainfall_mm, water: last.water_level_m - first.water_level_m }
  }, [detail])

  async function handleSubscribe(event) {
    event.preventDefault()
    setSubscribing(true)
    setSubResult(null)

    try {
      const res = await api.subscribe({ zone_slug: slug, email, phone: phone || null })
      setSubResult({ ok: true, message: res.message, smsNote: res.sms_note })
      setEmail('')
      setPhone('')
    } catch (e) {
      setSubResult({ ok: false, message: e.message })
    } finally {
      setSubscribing(false)
    }
  }

  if (loading) return <div className="page page-narrow"><div className="card muted">Loading flood status...</div></div>

  if (error) {
    return (
      <div className="page page-narrow">
        <div className="notice notice-error">Could not load flood status: {error}</div>
      </div>
    )
  }

  return (
    <div className="page page-narrow stack-16">
      <div>
        <h1>Is my area at flood risk?</h1>
        <p className="muted" style={{ marginTop: 6 }}>
          Pick your area to see today's flood risk, the latest inundation map, and get a warning
          before the water arrives.
        </p>
      </div>

      <div className="card">
        <label className="label" htmlFor="zone-picker">Your area</label>
        <select id="zone-picker" value={slug} onChange={(e) => setSlug(e.target.value)}>
          {zones.map((z) => (
            <option key={z.slug} value={z.slug}>
              {z.name} ({z.district})
            </option>
          ))}
        </select>
      </div>

      {selected && (
        <>
          <div className={`risk-hero hero-${selected.risk_level}`}>
            <div className="spread">
              <div>
                <div className="risk-hero-zone">
                  {selected.name}, {selected.district} district
                </div>
                <div className="risk-hero-level">{selected.risk_level}</div>
              </div>
              <RiskBadge level={selected.risk_level} />
            </div>
            <div className="risk-hero-advice">{selected.advice}</div>
          </div>

          <div className="metric-row">
            <div className="metric">
              <div className="metric-label">Rainfall, last 24h</div>
              <div className="metric-value">{selected.rainfall_mm ?? '-'} mm</div>
              {trend && (
                <div className="metric-note">
                  {trend.rain >= 0 ? 'Up' : 'Down'} {Math.abs(trend.rain).toFixed(1)} mm this week
                </div>
              )}
            </div>

            <div className="metric">
              <div className="metric-label">{selected.river ?? 'Water'} level</div>
              <div className="metric-value">{selected.water_level_m ?? '-'} m</div>
              <div className="metric-note">Danger level {selected.danger_level_m} m</div>
            </div>

            <div className="metric">
              <div className="metric-label">People in this zone</div>
              <div className="metric-value">{selected.population.toLocaleString('en-IN')}</div>
              <div className="metric-note">{selected.subscribers_count} subscribed to alerts</div>
            </div>
          </div>

          <div className="card">
            <div className="card-title">Why this level</div>
            <ul className="reason-list">
              {selected.reasons?.map((reason, i) => <li key={i}>{reason}</li>)}
            </ul>
          </div>

          <div className="card">
            <div className="spread" style={{ marginBottom: 4 }}>
              <div className="card-title">Inundation map</div>
              <span className="aws-tag">Amazon S3</span>
            </div>
            <div className="card-hint">
              The area expected to go under water in this zone, published by the control room.
            </div>

            {selected.inundation_map_url ? (
              <>
                <div className="map-preview">
                  <img src={selected.inundation_map_url} alt={`Inundation map for ${selected.name}`} />
                </div>
                <p className="small muted" style={{ marginTop: 8, marginBottom: 0 }}>
                  Updated {selected.inundation_map_updated_at
                    ? new Date(selected.inundation_map_updated_at).toLocaleString('en-IN')
                    : 'recently'}
                  .{' '}
                  <a href={selected.inundation_map_url} target="_blank" rel="noreferrer">Open full size</a>
                </p>
              </>
            ) : (
              <div className="notice notice-info">
                No inundation map published for {selected.name} yet. The control room adds one when
                a survey comes in.
              </div>
            )}
          </div>

          <div className="card">
            <div className="card-title">Where this is</div>
            <div className="card-hint">{selected.name} on the {selected.river ?? 'river'}.</div>
            <div className="map-shell tall">
              <ZoneMap zones={[selected]} selectedSlug={selected.slug} />
            </div>
          </div>

          <div className="card">
            <div className="spread" style={{ marginBottom: 4 }}>
              <div className="card-title">Get warned early</div>
              <span className="aws-tag">Amazon SNS</span>
            </div>
            <div className="card-hint">
              We will email you the moment {selected.name} reaches Warning or Severe.
            </div>

            <form onSubmit={handleSubscribe}>
              <div className="field">
                <label className="label" htmlFor="sub-email">Email</label>
                <input
                  id="sub-email"
                  type="email"
                  required
                  placeholder="you@example.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </div>

              <div className="field">
                <label className="label" htmlFor="sub-phone">Mobile number (optional)</label>
                <input
                  id="sub-phone"
                  type="tel"
                  placeholder="+91 98765 43210"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                />
                <p className="small muted" style={{ marginTop: 6, marginBottom: 0 }}>
                  SMS alerts need TRAI DLT registration, which is pending. Email alerts work now.
                </p>
              </div>

              <div className="field">
                <button className="btn btn-block" type="submit" disabled={subscribing}>
                  {subscribing ? 'Subscribing...' : `Alert me about ${selected.name}`}
                </button>
              </div>
            </form>

            {subResult && (
              <div className={`notice ${subResult.ok ? 'notice-ok' : 'notice-error'}`}>
                {subResult.message}
                {subResult.ok && subResult.smsNote && (
                  <div className="small" style={{ marginTop: 6 }}>{subResult.smsNote}</div>
                )}
              </div>
            )}
          </div>
        </>
      )}
    </div>
  )
}
