import { useEffect } from 'react'
import { CircleMarker, MapContainer, Popup, TileLayer, Tooltip, useMap } from 'react-leaflet'
import 'leaflet/dist/leaflet.css'
import { RISK_COLORS } from './RiskBadge'

/** Keeps the view on whatever zones are currently shown. */
function FitZones({ zones }) {
  const map = useMap()

  useEffect(() => {
    if (!zones.length) return

    const points = zones.map((z) => [z.latitude, z.longitude])

    if (points.length === 1) {
      map.setView(points[0], 9)
      return
    }

    map.fitBounds(points, { padding: [40, 40] })
  }, [map, zones])

  return null
}

/**
 * Leaflet map of monitored zones. Marker size and colour carry the risk level,
 * so an officer reads the district at a glance. Tiles are OpenStreetMap.
 */
export default function ZoneMap({ zones, selectedSlug, onSelect }) {
  return (
    <MapContainer center={[26.2, 92.5]} zoom={7} scrollWheelZoom={false}>
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />

      <FitZones zones={zones} />

      {zones.map((zone) => {
        const isSelected = zone.slug === selectedSlug
        const color = RISK_COLORS[zone.risk_level] ?? RISK_COLORS.Safe

        return (
          <CircleMarker
            key={zone.slug}
            center={[zone.latitude, zone.longitude]}
            radius={isSelected ? 15 : 8 + zone.risk_score * 2.5}
            pathOptions={{
              color: isSelected ? '#11212e' : color,
              weight: isSelected ? 3 : 2,
              fillColor: color,
              fillOpacity: 0.65,
            }}
            eventHandlers={onSelect ? { click: () => onSelect(zone.slug) } : undefined}
          >
            <Tooltip direction="top" offset={[0, -6]}>
              {zone.name}: {zone.risk_level}
            </Tooltip>

            <Popup>
              <strong>{zone.name}</strong>
              <br />
              {zone.district} district{zone.river ? ` / ${zone.river}` : ''}
              <br />
              Risk: <strong style={{ color }}>{zone.risk_level}</strong>
              <br />
              Rainfall 24h: {zone.rainfall_mm ?? '-'} mm
              <br />
              Water level: {zone.water_level_m ?? '-'} m (danger {zone.danger_level_m} m)
            </Popup>
          </CircleMarker>
        )
      })}
    </MapContainer>
  )
}
