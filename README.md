# JalRakshak

**Flood early warning and response for Assam.** Built for **Environmental Hacks**
(WeMakeDevs x AWS, Bharat Builds Tour, 8 to 11 October 2026).
Track: **Heat and Water (floods)**.

> **Live:** http://ec2-15-252-97-73.ap-south-1.compute.amazonaws.com/
> **Officer dashboard:** http://ec2-15-252-97-73.ap-south-1.compute.amazonaws.com/officer
> **Health check:** http://ec2-15-252-97-73.ap-south-1.compute.amazonaws.com/api/health
>
> Running on a single Amazon EC2 t3.micro in `ap-south-1`.

---

## The problem

Assam floods every monsoon. The Brahmaputra and the Barak cross their danger
levels nearly every year, and the gap that kills people is not a missing
forecast. The data exists. The gap is the last mile: the person in the village
finds out when the water is already at the door.

JalRakshak closes that gap. It watches rainfall and river levels per zone,
computes a risk level an officer can defend, and pushes a warning straight to
the people in that zone.

---

## What it does

### Officer dashboard (`/officer`)

The district command center. 30 villages across Assam on one screen.

- **Risk map** with every village coloured by its computed level, click through
  to a drawer with that village's rivers, shelters and alert history.
- **KPI strip**: villages above the danger mark, villages on warning, people at
  risk, open relief requests, alerts dispatched.
- **Risk donut** and **rainfall bars**, heaviest village first.
- **12 day trend**: average rainfall against people at risk.
- **Relief panel**: SOS requests citizens sent, each one acknowledgeable.
- **Activity feed** of everything that happened, newest first.
- **Replay slider**: step through the June 2022 Assam flood day by day, or
  switch to Live, which pulls current rainfall from Open-Meteo.
- **Satellite tab**: flood extent from a U-Net trained on Sen1Floods11. This is
  a trained model and is labelled as such, separate from the rule based engine
  everything else uses.
- **Send alert**: writes the warning in Hindi and English and publishes it to
  that village's Amazon SNS topic.

Behind a sign in, because it is on the public internet.

### Citizen page (`/`)

- Pick your village, see the risk level in one word, in Hindi or English.
- See why, in a sentence, and what to do about it.
- See the inundation map for your village, served from Amazon S3.
- See the nearest relief shelters.
- Subscribe to alerts for your village.

Mobile responsive, one column, large type. The SIH build served citizens through
an Android app; this hackathon is web only, so the same job is done by a page
that needs no install.

---

## Where each AWS service fits

| Service | Where it is used | Judging line |
|---|---|---|
| **Amazon EC2** | A single t3.micro instance runs the whole stack: Nginx serving the React build, PHP-FPM running the Laravel API, and MySQL. This is the deployed app and the live URL. | Deployed on AWS, which satisfies the eligibility rule on its own. |
| **Amazon S3** | Bucket holds the per-village inundation maps. Officers upload through the dashboard, Laravel writes to the bucket, and the citizen page reads them back through a presigned URL so the bucket itself stays private. | Durable object storage for the artefact that matters most to a citizen: the map of what goes under water. |
| **Amazon SNS** | One topic per village, 30 of them. A citizen subscribing creates an email subscription on their village's topic. "Send alert" is a single `Publish` that SNS fans out to every confirmed subscriber of that village. | Targeted fan-out that scales from 30 villages to 3,000 without the app changing. It also replaces the Firebase push path, so alerts now reach people with no app installed. |

Code pointers:

- S3: `api/app/Services/MapStorage.php`
- SNS: `api/app/Services/SnsService.php`
- EC2: `deploy/ec2-setup.sh`, `deploy/nginx-jalrakshak.conf`

---

## The risk engine

Rule based, not machine learning, and that is on purpose. A district control
room has to be able to explain why a warning went out. `api/app/Services/RiskEngine.php`
scores each village from three signals and returns the reason in Hindi and
English, which is the same text the citizen page and the alert email show.

- **Rainfall**, 24 hour and 3 day totals, against the India Meteorological
  Department categories.
- **River level**, against that gauge station's own warning and danger marks.
  Stations with no published thresholds skip this rule rather than guess, and
  the seeder says out loud which ones those are.
- **Elevation**, because the same rainfall means something different on low
  ground.

The score maps to **red**, **yellow** or **green**, and the engine also returns
`hours_to_danger` where the river is rising fast enough to estimate it.

The **Satellite** tab is the one exception: that flood extent comes from a U-Net
trained on Sen1Floods11, which is a trained model, not a rule. The UI keeps the
two visibly separate so nobody mistakes one for the other.

## SMS and DLT

Alerts go out as **email through Amazon SNS**, which works instantly and needs
no approval.

The citizen form also takes a phone number, and the number is stored, but SMS is
not sent. Sending SMS to Indian numbers requires TRAI DLT registration of the
sender ID and every template, which takes weeks and is a production step, not a
weekend one. The UI says so on screen rather than pretending otherwise. When DLT
clears, SNS sends SMS through the same topic with no application change.

---

## Tech stack

- **Backend:** Laravel 12, PHP 8.3
- **Frontend:** React 19 with Vite, React Router, Leaflet with OpenStreetMap tiles
- **Database:** MySQL 8, on the same EC2 instance
- **Cloud:** Amazon EC2, Amazon S3, Amazon SNS, region `ap-south-1` (Mumbai)

```
jalrakshak-aws/
  api/      Laravel 12 REST API
  web/      React + Vite frontend (officer dashboard + citizen page)
  data/     Assam demo CSVs the seeders read
  deploy/   Nginx config, EC2 setup script, IAM policy
```

---

## Demo data

30 villages across 17 river stations in Assam, seeded from the CSVs in `data/`.
Warning and danger marks are the Central Water Commission style values for those
gauge sites. Eleven stations have no published thresholds; those villages are
scored on rainfall and elevation only, and the seeder prints their names rather
than inventing numbers.

Two modes:

- **Replay 2022** steps through 12 days of the June 2022 Assam flood from
  `data/assam_2022_replay.csv`. This is what the demo uses, and both pages label
  it as a replay so it is never mistaken for today.
- **Live** pulls current rainfall from Open-Meteo. A scheduled
  `php artisan risk:compute --mode=live` keeps it fresh.

Six illustrative inundation maps ship in `api/database/seed-maps/` and the
seeder publishes them to Amazon S3 on every `migrate --seed`, so a fresh
environment, including the one `deploy/ec2-setup.sh` builds, comes up with maps
already in the bucket. They are marked as demo data on the image itself.

## Local setup

Needs PHP 8.3+, Composer, Node 20+, MySQL 8.

```bash
git clone https://github.com/shubhamverma-devx/jalrakshak-aws.git
cd jalrakshak-aws

# API
cd api
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE jalrakshak_aws CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# set DB_USERNAME and DB_PASSWORD in .env
php artisan migrate --seed
php artisan risk:compute --mode=replay --day=5
php artisan storage:link
php artisan serve

# Frontend, in a second terminal
cd ../web
npm install
npm run dev
```

Open http://localhost:5173 for the citizen page and
http://localhost:5173/officer for the dashboard.

Officer login for local development: `officer@jalrakshak.in` / `jalrakshak2026`,
set in `api/.env`.

The deployed instance does **not** use those. `deploy/ec2-setup.sh` generates a
random officer password and bearer token on first run, prints the password at
the end, and keeps it in `/root/.jalrakshak-officer-pass`. The officer console
is reachable from the internet, so it must not ship with a password that is
published in this README.

### Running without AWS

Out of the box `.env.example` ships with `MAPS_DISK=public` and
`SNS_ENABLED=false`, so the whole app runs offline. Maps are saved to local
disk and alerts are recorded with a note saying SNS is off, instead of failing.
Set `MAPS_DISK=s3` and `SNS_ENABLED=true` once credentials are in place.

---

## Deploying to Amazon EC2

On a fresh Ubuntu 24.04 t3.micro instance with ports 22 and 80 open:

```bash
curl -fsSL https://raw.githubusercontent.com/shubhamverma-devx/jalrakshak-aws/main/deploy/ec2-setup.sh -o setup.sh
chmod +x setup.sh
sudo ./setup.sh
```

The script installs Nginx, PHP 8.3-FPM, MySQL and Node, creates the database,
clones the repo, builds the React app, runs the migrations and seeders, and
writes the Nginx site. It is safe to re-run.

Then set `AWS_BUCKET` in `/var/www/jalrakshak-aws/api/.env`, and give the
instance AWS access one of two ways:

- **Preferred:** attach an IAM instance role with the policy in
  `deploy/iam-policy.json`. No keys on the server at all, and the SDK picks the
  role up automatically.
- Or set `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` in that `.env`.

Push an update later with `sudo /var/www/jalrakshak-aws/deploy/deploy.sh`.

---

## API

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/api/health` | no | Liveness, plus which AWS services are wired |
| GET | `/api/villages` | no | Every village with risk and summary, `?mode=live\|replay&day=N` |
| GET | `/api/village/{id}` | no | One village: river, shelters, alerts, inundation map |
| GET | `/api/village/{id}/forecast` | no | +24h / +48h forecast for that village |
| GET | `/api/shelters` | no | All relief shelters |
| GET | `/api/alerts` | no | Alert history |
| POST | `/api/relief` | no | A citizen sends an SOS |
| POST | `/api/subscribe` | no | Subscribe an email to a village's SNS topic |
| POST | `/api/register-token` | no | Android push registration |
| GET | `/api/sar/scenes` | no | Bundled SAR scenes and model provenance |
| POST | `/api/sar/detect` | no | Run the U-Net on a scene |
| POST | `/api/officer/login` | no | Returns the officer bearer token |
| POST | `/api/alert` | bearer | Publish a warning to Amazon SNS |
| GET | `/api/relief` | bearer | The officer's relief queue |
| PATCH | `/api/relief/{id}` | bearer | Change a relief request's status |
| POST | `/api/officer/village/{id}/map` | bearer | Upload an inundation map to Amazon S3 |

## What I would build next

- Pull readings automatically from the CWC and IMD feeds instead of by hand.
- SMS once DLT registration clears, and Assamese and Bengali alert text.
- A relief camp layer: capacity, occupancy and the safe route to the nearest one.
- Store the alert history in a way a district can audit after the season.

---

## Licence

MIT.
