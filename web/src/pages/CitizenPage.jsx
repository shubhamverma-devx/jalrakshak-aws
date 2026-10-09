/**
 * CitizenPage — gaon wale ke liye.
 *
 * SIH build mein citizen ka hissa Android app tha (Kotlin + Compose). Is hackathon mein
 * koi Android build nahi hai, isliye wahi kaam ek mobile-responsive web page karta hai:
 * apna gaon chuno, abhi ka risk dekho, inundation map dekho, aur alert subscribe karo.
 *
 * KYUN dashboard se alag page: officer ko 30 gaon ek saath chahiye. Gaon wale ko sirf
 * apna gaon chahiye, bade akshar mein, bina kisi jargon ke. Ek hi screen dono ko nahi
 * de sakti.
 *
 * Risk ka faisla yahan nahi hota — backend ke RiskEngine se aata hai, waise hi jaise
 * dashboard mein. Frontend sirf uska rang chunta hai.
 */

import { useCallback, useEffect, useMemo, useState } from 'react'
import { IconRipple, IconMapPin, IconBellRinging, IconAlertTriangle } from '@tabler/icons-react'

import { getVillages, getVillage, getAlerts, subscribe } from '../api/client'
import { LEVEL_COLORS } from '../config'

const LEVEL_LABEL = {
  red: { en: 'Danger', hi: 'खतरा' },
  yellow: { en: 'Warning', hi: 'चेतावनी' },
  green: { en: 'Safe', hi: 'सुरक्षित' },
}

/** localStorage key for the village someone picked last time. */
const LAST_VILLAGE = 'jalrakshak.citizen.village'

function readLastVillage() {
  try {
    return localStorage.getItem(LAST_VILLAGE)
  } catch {
    return null
  }
}

export default function CitizenPage() {
  // Dashboard ki tarah yahan bhi do mode hain. Default replay isliye hai ki demo mein
  // asli baadh dikhe; par screen par saaf likha hai ki ye June 2022 ka scenario hai,
  // warna "aaj ka risk" samajh ke koi galat faisla le sakta hai.
  const [mode, setMode] = useState(
    () => new URLSearchParams(window.location.search).get('mode') || 'replay',
  )
  const [day, setDay] = useState(5)
  const [asOf, setAsOf] = useState(null)
  const [villages, setVillages] = useState([])
  const [villageId, setVillageId] = useState(null)
  const [detail, setDetail] = useState(null)
  const [alerts, setAlerts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [hindi, setHindi] = useState(true)

  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [subBusy, setSubBusy] = useState(false)
  const [subResult, setSubResult] = useState(null)

  // Alert emails link here as /?village=12.
  const initialId = useMemo(() => {
    const fromUrl = new URLSearchParams(window.location.search).get('village')
    return fromUrl || readLastVillage()
  }, [])

  useEffect(() => {
    let alive = true
    setLoading(true)

    getVillages(mode, mode === 'replay' ? day : undefined)
      .then((res) => {
        if (!alive) return
        const list = [...res.villages].sort((a, b) => a.name.localeCompare(b.name))
        setVillages(list)
        setAsOf(res.date ?? null)

        setVillageId((current) => {
          if (current) return current
          const wanted = list.find((v) => String(v.id) === String(initialId))
          // Jo gaon sabse zyada khatre mein hai wahi default — page kabhi khaali na lage.
          const worst = [...res.villages].sort((a, b) => (b.risk?.score ?? 0) - (a.risk?.score ?? 0))[0]
          return wanted?.id ?? worst?.id ?? list[0]?.id ?? null
        })
      })
      .catch((e) => alive && setError(e.message))
      .finally(() => alive && setLoading(false))

    return () => {
      alive = false
    }
  }, [initialId, mode, day])

  const loadVillage = useCallback((id) => {
    if (!id) return
    setSubResult(null)

    getVillage(id, mode, mode === 'replay' ? day : undefined)
      .then(setDetail)
      .catch((e) => setError(e.message))
    getAlerts()
      .then((res) => setAlerts(res.alerts.filter((a) => a.village?.id === Number(id))))
      .catch(() => setAlerts([]))
  }, [mode, day])

  useEffect(() => {
    if (!villageId) return

    try {
      localStorage.setItem(LAST_VILLAGE, String(villageId))
    } catch {
      /* private window, not important enough to tell anyone about */
    }

    loadVillage(villageId)
  }, [villageId, loadVillage])

  const village = detail?.village ?? villages.find((v) => v.id === villageId)
  const risk = village?.risk
  const level = risk?.level ?? 'green'
  const colour = LEVEL_COLORS[level]

  async function handleSubscribe(e) {
    e.preventDefault()
    setSubBusy(true)
    setSubResult(null)

    try {
      const res = await subscribe({ village_id: villageId, email, phone: phone || null })
      setSubResult({ ok: true, text: res.message, sms: res.sms_note })
      setEmail('')
      setPhone('')
    } catch (err) {
      setSubResult({ ok: false, text: err.message })
    } finally {
      setSubBusy(false)
    }
  }

  if (loading) {
    return (
      <div className="cz">
        <div className="cz-wrap">
          <div className="sk cz-sk" />
        </div>
      </div>
    )
  }

  if (error && !village) {
    return (
      <div className="cz">
        <div className="cz-wrap">
          <div className="errbox">{error}</div>
        </div>
      </div>
    )
  }

  const t = (hi, en) => (hindi ? hi : en)

  return (
    <div className="cz">
      <header className="cz-top">
        <div className="logo">
          <span className="mk">
            <IconRipple className="ti" />
          </span>
          JalRakshak
        </div>

        <div className="spacer" />

        <div className="seg cz-lang">
          <button className={mode === 'replay' ? 'active' : ''} onClick={() => setMode('replay')}>
            {t('2022 रीप्ले', 'Replay 2022')}
          </button>
          <button className={mode === 'live' ? 'active' : ''} onClick={() => setMode('live')}>
            {t('अभी', 'Live')}
          </button>
        </div>

        <div className="seg cz-lang">
          <button className={hindi ? 'active' : ''} onClick={() => setHindi(true)}>
            हिंदी
          </button>
          <button className={!hindi ? 'active' : ''} onClick={() => setHindi(false)}>
            English
          </button>
        </div>
      </header>

      <div className="cz-wrap">
        <h1 className="cz-h1">
          {t('मेरे गाँव में बाढ़ का खतरा है क्या?', 'Is my village at flood risk?')}
        </h1>

        <div className={`cz-mode${mode === 'replay' ? ' replay' : ''}`}>
          {mode === 'replay'
            ? t(
                `यह जून 2022 की असम बाढ़ का रीप्ले है${asOf ? ` (${asOf})` : ''}, आज का हाल नहीं। आज का देखने के लिए ऊपर "अभी" दबाएँ।`,
                `This is a replay of the June 2022 Assam flood${asOf ? ` (${asOf})` : ''}, not today. Press "Live" above for today.`,
              )
            : t('यह अभी का हाल है, Open-Meteo के ताज़ा डेटा से।', 'This is today, from live Open-Meteo data.')}
        </div>

        <label className="fld-lbl" htmlFor="cz-village">
          <IconMapPin className="ti" /> {t('अपना गाँव चुनें', 'Choose your village')}
        </label>
        <select
          id="cz-village"
          className="fld cz-select"
          value={villageId ?? ''}
          onChange={(e) => setVillageId(Number(e.target.value))}
        >
          {villages.map((v) => (
            <option key={v.id} value={v.id}>
              {v.name} ({v.district})
            </option>
          ))}
        </select>

        {village && risk && (
          <>
            <section className="cz-hero" style={{ borderColor: colour, background: `${colour}1a` }}>
              <div className="cz-hero-top">
                <div>
                  <div className="cz-hero-place">
                    {village.name}, {village.district}
                  </div>
                  <div className="cz-hero-level" style={{ color: colour }}>
                    {t(LEVEL_LABEL[level].hi, LEVEL_LABEL[level].en)}
                  </div>
                </div>
                <span className="rdot" style={{ background: colour, width: 18, height: 18 }} />
              </div>

              <p className={`cz-hero-advice${hindi ? ' hindi' : ''}`}>
                {t(risk.advice_hi, risk.advice_en)}
              </p>
            </section>

            <div className="cz-facts">
              <div className="kpi">
                <div className="cz-fact-lbl">{t('बारिश, 24 घंटे', 'Rainfall, 24h')}</div>
                <div className="cz-fact-val mono">{risk.factors?.rainfall_mm ?? '-'} mm</div>
              </div>

              <div className="kpi">
                <div className="cz-fact-lbl">{t('नदी का स्तर', 'River level')}</div>
                <div className="cz-fact-val mono">
                  {risk.factors?.river_level_m != null ? `${risk.factors.river_level_m} m` : '-'}
                </div>
                <div className="cz-fact-note">
                  {risk.factors?.danger_level_m != null
                    ? `${t('खतरे का स्तर', 'Danger level')} ${risk.factors.danger_level_m} m`
                    : t('नदी का डेटा नहीं', 'No river data')}
                </div>
              </div>

              <div className="kpi">
                <div className="cz-fact-lbl">{t('गाँव की आबादी', 'People here')}</div>
                <div className="cz-fact-val mono">
                  {(village.population ?? 0).toLocaleString('en-IN')}
                </div>
              </div>
            </div>

            <section className="panel cz-panel">
              <div className="phead">
                <span className="ptitle">{t('ऐसा क्यों', 'Why this level')}</span>
              </div>
              <div className="pbody">
                <p className={hindi ? 'hindi' : ''}>{t(risk.reason_hi, risk.reason_en)}</p>
                <p className={`cz-eta${hindi ? ' hindi' : ''}`}>
                  {t(risk.water_eta_hi, risk.water_eta_en)}
                </p>
              </div>
            </section>

            <section className="panel cz-panel">
              <div className="phead">
                <span className="ptitle">{t('बाढ़ का नक्शा', 'Inundation map')}</span>
                <span className="chip">Amazon S3</span>
              </div>
              <div className="pbody">
                {detail?.inundation_map?.url ? (
                  <>
                    <img
                      className="cz-map"
                      src={detail.inundation_map.url}
                      alt={`Inundation map for ${village.name}`}
                    />
                    <p className="cz-note">
                      {t(
                        'यह वह इलाका है जो पानी में डूब सकता है। यह नक्शा जून 2022 की बाढ़ के लिए बना है।',
                        'This is the area expected to go under water. The map was published for the June 2022 flood.',
                      )}
                    </p>
                  </>
                ) : (
                  <p className="cz-note">
                    {t(
                      'इस गाँव का नक्शा अभी नहीं आया है। कंट्रोल रूम सर्वे आने पर डालता है।',
                      'No map published for this village yet. The control room adds one when a survey comes in.',
                    )}
                  </p>
                )}
              </div>
            </section>

            {detail?.shelters?.length > 0 && (
              <section className="panel cz-panel">
                <div className="phead">
                  <span className="ptitle">{t('सबसे नज़दीकी राहत शिविर', 'Nearest relief shelters')}</span>
                </div>
                <div className="pbody">
                  {detail.shelters.slice(0, 3).map((sh) => (
                    <div className="cz-shelter" key={sh.id}>
                      <div>
                        <strong>{sh.name}</strong>
                        <div className="cz-note">{sh.district}</div>
                      </div>
                      <div className="mono cz-shelter-cap">
                        {sh.capacity ? `${sh.capacity} ${t('लोग', 'people')}` : ''}
                      </div>
                    </div>
                  ))}
                </div>
              </section>
            )}

            <section className="panel cz-panel">
              <div className="phead">
                <span className="ptitle">
                  <IconBellRinging className="ti" /> {t('पहले से चेतावनी पाएँ', 'Get warned early')}
                </span>
                <span className="chip">Amazon SNS</span>
              </div>
              <div className="pbody">
                <p className="cz-note">
                  {t(
                    `${village.name} खतरे में आते ही हम आपको ईमेल भेज देंगे।`,
                    `We will email you the moment ${village.name} moves into danger.`,
                  )}
                </p>

                <form onSubmit={handleSubscribe}>
                  <label className="fld-lbl" htmlFor="cz-email">{t('ईमेल', 'Email')}</label>
                  <input
                    id="cz-email"
                    className="fld"
                    type="email"
                    required
                    placeholder="you@example.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                  />

                  <label className="fld-lbl" htmlFor="cz-phone">
                    {t('मोबाइल नंबर (ज़रूरी नहीं)', 'Mobile number (optional)')}
                  </label>
                  <input
                    id="cz-phone"
                    className="fld"
                    type="tel"
                    placeholder="+91 98765 43210"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                  />
                  <div className="fld-help">
                    {t(
                      'SMS के लिए TRAI DLT रजिस्ट्रेशन चाहिए, जो अभी बाकी है। ईमेल अभी चालू है।',
                      'SMS needs TRAI DLT registration, which is still pending. Email works now.',
                    )}
                  </div>

                  <button className="btn-primary cz-sub-btn" type="submit" disabled={subBusy}>
                    {subBusy
                      ? t('भेजा जा रहा है...', 'Subscribing...')
                      : t('मुझे चेतावनी भेजें', 'Alert me')}
                  </button>
                </form>

                {subResult && (
                  <div className={subResult.ok ? 'sent-ok cz-sub-msg' : 'errbox cz-sub-msg'}>
                    {subResult.text}
                    {subResult.ok && subResult.sms && <div className="cz-note">{subResult.sms}</div>}
                  </div>
                )}
              </div>
            </section>

            {alerts.length > 0 && (
              <section className="panel cz-panel">
                <div className="phead">
                  <span className="ptitle">
                    <IconAlertTriangle className="ti" /> {t('पिछली चेतावनियाँ', 'Recent alerts')}
                  </span>
                </div>
                <div className="pbody">
                  {alerts.slice(0, 5).map((a) => (
                    <div className="cz-alert" key={a.id}>
                      <div className={hindi ? 'hindi' : ''}>{t(a.message_hi, a.message_en)}</div>
                      <div className="cz-note mono">
                        {new Date(a.sent_at).toLocaleString('en-IN')} · {a.sent_by}
                      </div>
                    </div>
                  ))}
                </div>
              </section>
            )}
          </>
        )}

        <footer className="cz-foot">
          JalRakshak runs on AWS: Amazon EC2 hosts it, Amazon S3 holds the inundation maps,
          Amazon SNS delivers the alerts. <a href="/officer">Officer sign in</a>
        </footer>
      </div>
    </div>
  )
}
