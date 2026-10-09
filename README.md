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

- Map of every monitored zone, marker colour and size follow the computed risk.
- Table of zones with rainfall in the last 24 hours, current water level, that
  zone's own warning and danger marks, and the computed risk.
- **Trigger alert** per zone: publishes a flood warning to that zone's Amazon
  SNS topic, which fans out to every subscriber of that zone.
- **Map** per zone: uploads an inundation map (image or GeoJSON) to Amazon S3.
- **+40mm** per zone: pushes a wetter reading, so you can watch a zone move from
  Warning to Severe live. This is where a real rain gauge feed would post.

### Citizen page (`/`)

- Pick your area, see the risk level in plain words with advice you can act on.
- See why: the exact rules that produced that level, in sentences.
- See the latest inundation map for your area, served from Amazon S3.
- Subscribe to alerts for your zone.

Mobile responsive. Readable in ten seconds by someone who has never seen it.

---

## Where each AWS service fits

| Service | Where it is used | Judging line |
|---|---|---|
| **Amazon EC2** | A single t3.micro instance runs the whole stack: Nginx serving the React build, PHP-FPM running the Laravel API, and MySQL. This is the deployed app and the live URL. | Deployed on AWS, which satisfies the eligibility rule on its own. |
| **Amazon S3** | Bucket holds the per-zone inundation maps. Officers upload through the dashboard, Laravel writes to the bucket, and the citizen page reads them back through a presigned URL so the bucket itself stays private. | Durable object storage for the artefact that matters most to a citizen: the map of what goes under water. |
| **Amazon SNS** | One topic per zone. A citizen subscribing to their area creates an email subscription on that zone's topic. "Trigger alert" is a single `Publish` that SNS fans out to every confirmed subscriber of that zone. | Fan-out delivery that scales from 8 zones to 800 without the app changing. |

Code pointers:

- S3: `api/app/Services/MapStorage.php`
- SNS: `api/app/Services/SnsService.php`
- EC2: `deploy/ec2-setup.sh`, `deploy/nginx-jalrakshak.conf`

---

## The risk engine

Deliberately rule based, not machine learning. A district control room has to be
able to explain why a warning went out, and a weekend model cannot be audited.
Every level traces back to a published threshold.

`api/app/Services/RiskEngine.php`

**Rule 1, rainfall in the last 24 hours.** India Meteorological Department
categories:

| Rainfall | Score |
|---|---|
| under 64.5 mm | 0 |
| 64.5 mm and above, heavy | 1 |
| 115.6 mm and above, very heavy | 2 |
| 204.5 mm and above, extremely heavy | 3 |

**Rule 2, water level.** Against that zone's own marks, in the style the Central
Water Commission publishes per gauge site:

| Water level | Score |
|---|---|
| more than 1 m below the warning level | 0 |
| within 1 m of the warning level | 1 |
| at or above the warning level | 2 |
| at or above the danger level | 3 |

**Rule 3.** The worse of the two scores sets the floor.

**Rule 4.** If rainfall and water level are both at 2 or more, escalate one step.
Heavy rain landing on an already high river is worse than either signal alone.

Score maps to **Safe**, **Watch**, **Warning**, **Severe**. Alerts go out at
Warning and above. Every assessment returns its reasons as sentences, and those
sentences appear on the citizen page and inside the alert email.

Covered by `api/tests/Unit/RiskEngineTest.php`.

---

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
  web/      React + Vite frontend
  deploy/   Nginx config, EC2 setup script, IAM policy
```

---

## Demo data

Eight zones along the Brahmaputra, the Barak and the Kopili: Dibrugarh,
Neamatighat (Jorhat), Tezpur, Pandu (Guwahati), Goalpara, Dhubri, Annapurna Ghat
(Silchar) and Kampur (Nagaon).

Warning and danger levels are the CWC style marks for those gauge sites, rounded
for the demo. Each zone carries seven days of readings so the dashboard shows a
trend, and the current readings are set so that two zones sit at each risk
level.

Four illustrative inundation maps ship in `api/database/seed-maps/`, and the
seeder publishes them to Amazon S3 on every `migrate --seed`. A fresh
environment, including the one `deploy/ec2-setup.sh` builds, therefore comes up
with maps already in the bucket. They are clearly marked as demo data on the
image itself. If AWS credentials are missing the seeder reports it and carries
on, rather than failing the seed.

---

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
| GET | `/api/health` | no | Liveness plus which AWS services are wired |
| GET | `/api/zones` | no | Every zone with its current reading and risk |
| GET | `/api/zones/{slug}` | no | One zone, with trend, map and alert history |
| GET | `/api/alerts` | no | The alert log |
| POST | `/api/subscribe` | no | Subscribe an email to a zone's SNS topic |
| POST | `/api/officer/login` | no | Returns the officer bearer token |
| POST | `/api/officer/zones/{slug}/readings` | bearer | Record a new reading |
| POST | `/api/officer/zones/{slug}/map` | bearer | Upload an inundation map to S3 |
| POST | `/api/officer/zones/{slug}/alert` | bearer | Publish a flood warning to SNS |

---

## Notes on the deployment

- Credentials: the instance uses an **IAM instance role**
  (`jalrakshak-ec2-role`), so there is no AWS key anywhere on the server.
- Security group: port 80 open to the world, port 22 restricted to a single
  operator IP.
- IMDSv2 is required on the instance, and the setup script fetches a metadata
  token accordingly.
- `composer.json` pins `config.platform.php` to 8.3, so the lock file resolved
  on a developer machine is installable on Ubuntu 24.04, which ships PHP 8.3.
- The box has 2 GB of swap, added before the build, because t3.micro has under
  1 GB of RAM.
- Presigned S3 URLs are generated per request and kept short lived. Alert emails
  link to `/?zone=<slug>` on the site rather than embedding a presigned URL,
  because a URL signed with instance role credentials expires with them.

## What I would build next

- Pull readings automatically from the CWC and IMD feeds instead of by hand.
- SMS once DLT registration clears, and Assamese and Bengali alert text.
- A relief camp layer: capacity, occupancy and the safe route to the nearest one.
- Store the alert history in a way a district can audit after the season.

---

## Licence

MIT.
