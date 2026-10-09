# JalRakshak, 3 minute demo video script

Total 3:00. Timings are the spoken length, so leave a little room.

**Before you record**

1. `php artisan migrate:fresh --seed` on the server, so the board starts clean
   at two zones per risk level.
2. Subscribe your own email to **Tezpur** on the citizen page, and click the
   confirm link Amazon SNS sends you. Without that confirmation no email arrives.
3. Upload an inundation map image for Tezpur from the officer dashboard.
4. Have your inbox open in a second tab, already filtered to the SNS sender.
5. Record at 1080p. Zoom the browser to about 125 percent so the table is
   readable after compression.

---

## 1. The problem, 0:00 to 0:20

> Every monsoon the Brahmaputra crosses its danger level and Assam floods. The
> forecast is not the missing piece. The data exists. What is missing is the
> last mile. The person in the village finds out when the water is already at
> the door.
>
> JalRakshak closes that gap.

_On screen: the citizen page, a Severe zone showing._

---

## 2. Citizen page, 0:20 to 1:00

_Pick Tezpur from the dropdown._

> This is the citizen side. I pick my area and I get one word: the risk level
> right now, and what to do about it.

_Scroll to the reasons list._

> And it tells me why. Not a black box score. The actual rules: rainfall in the
> last 24 hours, and the river level against Tezpur's own danger mark.

_Scroll to the inundation map._

> This is the inundation map for my area, the part that goes under water. It is
> stored in **Amazon S3** and served here through a presigned URL.

_Scroll to the subscribe form, type your email._

> And I subscribe, so I get warned before the water arrives, not after.

---

## 3. Officer dashboard, 1:00 to 1:50

_Switch to `/officer`._

> This is the district control room. Eight monitored zones across Assam, on the
> Brahmaputra, the Barak and the Kopili.

_Point at the map._

> Every zone, coloured by risk. Green is safe, red is severe.

_Point at the table._

> And the numbers behind it. Rainfall in the last 24 hours, the current water
> level, and each zone's own warning and danger marks, because 28 metres means
> something different at Dhubri than it does at Tezpur.

_Find the Tezpur row. It should read Warning._

> Tezpur is at Warning. Now the rain picks up.

_Press **+40mm** on the Tezpur row._

> Forty more millimetres, and the risk flips from Warning to Severe. Rule four:
> heavy rain landing on an already high river escalates a step.

_Press **Trigger alert** on the Tezpur row._

> One press. That publishes to Tezpur's **Amazon SNS** topic, and SNS fans it
> out to everyone subscribed to that zone.

---

## 4. The alert lands, 1:50 to 2:10

_Switch to your inbox. The email should already be there._

> And it is already here.

_Open it._

> The level, the rainfall, the water level against the danger mark, the exact
> reasons, what to do, and a link to the inundation map. The same explanation
> the officer saw.

---

## 5. Where AWS fits, 2:10 to 2:40

_Back to the dashboard, or a simple architecture slide._

> Three AWS services, each doing a real job.
>
> **Amazon EC2** runs the whole thing. One t3.micro instance: Nginx serving the
> React build, PHP-FPM running the Laravel API, MySQL alongside it. That is the
> URL you are looking at.
>
> **Amazon S3** holds the inundation maps. Officers upload, citizens read.
>
> **Amazon SNS** delivers the alerts. One topic per zone, one publish, fanned
> out to every subscriber. Eight zones today, eight hundred tomorrow, no change
> to the application.

---

## 6. Close, 2:40 to 3:00

> Alerts go by email today. SMS to Indian numbers needs TRAI DLT registration,
> which takes weeks, so the number field is there and the app says so honestly
> rather than faking it.
>
> Next I would pull the readings straight from the CWC and IMD feeds, send the
> alerts in Assamese and Bengali, and add a relief camp layer with the safe
> route to the nearest one.
>
> JalRakshak. Built on AWS. Thank you.

---

## Things to say out loud, for the judging rubric

Name all three services clearly: **Amazon EC2**, **Amazon S3**, **Amazon SNS**.
Say **"deployed on AWS"** once. Show the live URL in the address bar at least
twice.

## If something breaks on the day

- Email does not arrive: the subscription was never confirmed. Check the SNS
  console, Subscriptions, status must be **Confirmed**, not Pending.
- Risk does not move on **+40mm**: that zone was already Severe. Use a Watch or
  Warning zone instead.
- Map does not show: `AWS_BUCKET` is unset in `api/.env` on the server, or the
  IAM role is missing `s3:GetObject`.
- Hit `/api/health` on the live URL. It says whether SNS and S3 are wired.
