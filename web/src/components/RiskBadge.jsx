export const RISK_COLORS = {
  Safe: '#15803d',
  Watch: '#b45309',
  Warning: '#d9480f',
  Severe: '#b91c1c',
}

export default function RiskBadge({ level }) {
  return (
    <span className={`risk-pill risk-${level}`}>
      <span className="risk-dot" aria-hidden="true" />
      {level}
    </span>
  )
}
