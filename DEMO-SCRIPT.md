# JalRakshak, 3 minute demo video script

Total 3:00. Timings are the spoken length, so leave a little room.

**The URLs**

- Citizen: http://ec2-15-252-97-73.ap-south-1.compute.amazonaws.com/
- Officer: http://ec2-15-252-97-73.ap-south-1.compute.amazonaws.com/officer
- Officer login: `officer@jalrakshak.in`, password printed by the setup script
  and kept in `/root/.jalrakshak-officer-pass` on the instance.

**Before you record**

1. On the citizen page, pick **Cachar** and subscribe your own email. Then open
   your inbox and click **Confirm subscription** in the mail from AWS. Nothing
   arrives without that click, and the dashboard will honestly tell you nobody
   is subscribed if you skip it.
2. Sign in to the officer dashboard and leave it on **Replay 2022**, day 1.
3. Have your inbox open in a second tab.
4. Record at 1080p. The dashboard is dense; do not shrink the window.

---

## 1. The problem, 0:00 to 0:20

> Every monsoon the Brahmaputra and the Barak cross their danger levels and
> Assam floods. The forecast is not the missing piece. The data exists. What is
> missing is the last mile. The person in the village finds out when the water
> is already at the door.
>
> JalRakshak closes that gap.

_On screen: the officer dashboard, replay at day 1, mostly green._

---

## 2. Officer dashboard, 0:20 to 1:20

> This is a district command center. Thirty villages across Assam, every one
> scored right now.

_Drag the replay slider from day 1 towards day 6._

> This is the June 2022 flood, replayed day by day. Watch the map.

_Let it run. Green turns amber, then red._

> Rainfall climbs, the rivers cross their warning marks, and by the twentieth of
> June eighteen villages are above the danger line. A hundred and sixty five
> thousand people.

_Click a red village on the map to open the drawer._

> And it tells you why. Not a score out of a black box. The rainfall, the river
> level against that station's own danger mark, the elevation. The same sentence
> in Hindi and English, because that is what goes out to people.

_Point at the Satellite tab, do not open it unless you have time._

> The Satellite tab is a separate thing: flood extent from a U-Net trained on
> Sen1Floods11. It is a trained model, so the interface keeps it visibly apart
> from the rule based engine everything else uses.

---

## 3. The alert, 1:20 to 2:00

_Press **Send alert** in the top bar. Pick Cachar._

> This is the only action an officer actually takes here. Pick the village,
> check the Hindi and the English, send.

_Send it._

> That is one publish to Cachar's own **Amazon SNS** topic, and SNS fans it out
> to everyone subscribed to that village. Not a blanket district SMS. Just the
> village that needs it.

_Switch to your inbox. The mail should already be there._

> And it is already here. The level, what to do, and a link straight to the
> page for that village.

---

## 4. Citizen page, 2:00 to 2:30

_Follow the link in the email, or open the citizen page._

> This is what a person in Cachar sees. One word: danger. What to do, in Hindi.
> Why, in a sentence.

_Scroll to the inundation map._

> The inundation map for their own village, stored in **Amazon S3** and served
> through a presigned URL, so the bucket itself stays private.

_Scroll to shelters, then the subscribe box._

> The nearest relief shelter. And the box that put them on the alert list in
> the first place.

---

## 5. Where AWS fits, 2:30 to 2:50

> Three AWS services, each doing a real job.
>
> **Amazon EC2** runs all of it. One t3.micro: Nginx serving the React build,
> PHP-FPM running the Laravel API, MySQL alongside. That is the URL you are
> looking at.
>
> **Amazon S3** holds the inundation maps.
>
> **Amazon SNS** delivers the alerts, one topic per village.
>
> No AWS keys on that server, by the way. It uses an IAM instance role.

---

## 6. Close, 2:50 to 3:00

> Alerts go by email today. SMS to Indian numbers needs TRAI DLT registration,
> which takes weeks, so the app says so on screen instead of faking it.
>
> Next: pull the river levels straight from the CWC feed, and Assamese alongside
> Hindi and English.
>
> JalRakshak. Built on AWS. Thank you.

---

## Things to say out loud, for the judging rubric

Name all three services clearly: **Amazon EC2**, **Amazon S3**, **Amazon SNS**.
Say **"deployed on AWS"** once. Show the live URL in the address bar at least
twice.

## If something breaks on the day

- **Email does not arrive.** The subscription was never confirmed. The dashboard
  will have said so: "nobody in this village has confirmed an alert subscription
  yet". Check the SNS console, Subscriptions, status must be a real ARN, not
  `PendingConfirmation`.
- **Dashboard is empty.** `risk:compute` has not run.
  `sudo php artisan risk:compute --mode=replay --day=5` in
  `/var/www/jalrakshak-aws/api`.
- **Live mode shows everything green.** That is probably correct, it is not the
  monsoon. Use Replay 2022 for the demo.
- **Map missing on a village.** Only six villages ship with a demo map. Cachar,
  Barpeta, Dhubri, Goalpara, South Salmara and Morigaon all have one.
- **Anything else.** Hit `/api/health` on the live URL. It says whether the
  database is up and whether SNS and S3 are wired.

## Reset to a clean demo state

```bash
ssh -i ~/.ssh/jalrakshak-aws.pem ubuntu@15.252.97.73
cd /var/www/jalrakshak-aws/api
sudo php artisan migrate:fresh --seed --force
sudo php artisan risk:compute --mode=replay --day=5
sudo php artisan jalrakshak:sns-setup
```

That clears alerts and relief requests and republishes the maps. It also clears
subscribers, so subscribe and confirm your email again afterwards.
