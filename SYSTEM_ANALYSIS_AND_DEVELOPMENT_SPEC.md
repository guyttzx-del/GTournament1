# เอกสารวิเคราะห์และข้อกำหนดพัฒนาเว็บไซต์ศูนย์กลางการแข่งขัน eFootball

> เอกสารฉบับนี้วิเคราะห์รูปแบบการทำงานที่มองเห็นได้จากเว็บไซต์อ้างอิง `https://bomzaghi4.com/` ณ วันที่ 17 สิงหาคม 2026 แล้วแปลงเป็นข้อกำหนดสำหรับสร้างเว็บไซต์ใหม่ด้วย PHP + Supabase ในธีมดำ–แดง–ขาว
>
> เป้าหมายคือศึกษาแนวคิดและลำดับการใช้งาน ไม่ใช่คัดลอกชื่อแบรนด์ โลโก้ ข้อความ รูปภาพ ข้อมูลผู้เล่น หรือทรัพย์สินทางปัญญาของเว็บไซต์อ้างอิง ควรสร้างชื่อ เนื้อหา และอัตลักษณ์ใหม่ทั้งหมด

## 1. ขอบเขตและข้อจำกัดของการวิเคราะห์

### ส่วนที่ตรวจสอบได้จากหน้าสาธารณะ

- Dashboard / หน้าแรก
- รายการทัวร์นาเมนต์และตัวกรองสถานะ
- ขั้นตอนสมัครแข่งขัน
- ทำเนียบแชมป์ราย Season
- อันดับผู้เล่นแบบ Current, All-Time และแยกเวอร์ชันเกม
- อันดับคลับแบบ Current และ All-Time
- Hall of Fame หลายหมวด
- คลังคลิป YouTube
- Matchmaking สำหรับเปรียบเทียบผู้เล่น 2 คน
- About และ Contact
- หน้าต่างเข้าสู่ระบบสำหรับทีมงาน

### ส่วนที่ตรวจสอบไม่ได้

ระบบหลังบ้านหลังเข้าสู่ระบบไม่สามารถตรวจสอบได้เพราะไม่มีบัญชีทีมงาน ดังนั้นรายละเอียดหลังบ้านในเอกสารนี้เป็นข้อเสนอที่อนุมานจากข้อมูลและฟังก์ชันฝั่งสาธารณะ ไม่ใช่การยืนยันโครงสร้างภายในของเว็บไซต์อ้างอิง

## 2. ภาพรวมระบบที่ควรพัฒนา

เว็บไซต์ใหม่ควรเป็น Tournament Community Hub ที่รวมงาน 4 ด้านไว้ในระบบเดียว

1. **การจัดการแข่งขัน** — สร้าง Season เปิดรับสมัคร จัดกลุ่ม บันทึกผล และปิดฤดูกาล
2. **ฐานข้อมูลผู้เล่นและคลับ** — เก็บตัวตน ผลงาน สังกัด และประวัติอย่างต่อเนื่อง
3. **สถิติและคอนเทนต์สาธารณะ** — Ranking, Hall of Fame, ทำเนียบแชมป์, คลิป และ Activity Feed
4. **ระบบทีมงาน** — ตรวจใบสมัคร ตรวจสลิป จัดสาย บันทึกผล ปรับข้อมูล และเผยแพร่เนื้อหา

## 3. บทบาทผู้ใช้งาน

| บทบาท | ความสามารถหลัก |
|---|---|
| ผู้เยี่ยมชม | ดูรายการแข่งขัน อันดับ สถิติ คลิป และข้อมูลสาธารณะ |
| ผู้สมัคร | ค้นหาประวัติเดิมหรือสร้างผู้เล่นใหม่ ส่งใบสมัครและหลักฐานชำระเงิน |
| ผู้เล่นที่ยืนยันตัวตน | แก้ไขโปรไฟล์ของตน ติดตามใบสมัคร ดูคู่แข่งและผลส่วนตัว |
| เจ้าของคลับ | จัดการรายละเอียดคลับและคำขอเข้า/ออกของสมาชิกตามสิทธิ์ |
| Staff | ตรวจใบสมัครและสลิป บันทึกผล จัดการข้อมูลการแข่งขัน |
| Admin | สิทธิ์ Staff ทั้งหมด จัดการผู้ใช้ สิทธิ์ การตั้งค่า และ Audit Log |

ข้อเสนอสำหรับรุ่นแรก: เปิดให้สมัครแบบไม่ต้องสร้างบัญชีได้ แต่ส่งลิงก์ติดตามสถานะที่มี token แบบใช้ครั้งเดียว หรือให้ยืนยันอีเมลก่อนแก้ไขข้อมูลภายหลัง ทั้งนี้ข้อมูลส่วนบุคคลต้องไม่แสดงบนหน้าสาธารณะโดยอัตโนมัติ

## 4. โครงสร้างหน้าสาธารณะ

### 4.1 Dashboard `/`

องค์ประกอบหลัก:

- Hero พร้อมข้อความแนะนำและปุ่ม “สมัครแข่งขัน” / “ดูอันดับผู้เล่น”
- แถบความเคลื่อนไหวล่าสุด
- Season ที่เปิดรับสมัคร พร้อมจำนวนที่นั่งและวันปิดรับ
- แชมป์ล่าสุด
- Top 5 Player Ranking และ Club Ranking
- คลิปล่าสุดแบบ carousel
- Activity Feed เช่น สมัครใหม่ ผลการแข่งขัน และประกาศแชมป์
- ผู้เล่นที่น่าจับตา

ข้อมูลควรดึงจาก view หรือ RPC ที่เตรียมไว้ เพื่อลดจำนวนคำขอจากหน้าแรก

### 4.2 Tournaments `/tournaments`

- ตัวกรอง: ทั้งหมด, เปิดรับสมัคร, กำลังแข่งขัน, จบแล้ว
- การ์ดแต่ละ Season: ปก, ชื่อ, ประเภท, เวอร์ชันเกม, สถานะ, วันสำคัญ, จำนวนผู้สมัคร
- หน้า Detail `/tournaments/{slug}`: รายละเอียด กติกา ตารางแข่ง กลุ่ม ผล และผู้ชนะ
- ปุ่มสมัครแสดงเฉพาะเมื่ออยู่ในช่วงรับสมัครและยังมีที่นั่ง

สถานะมาตรฐานที่แนะนำ:

`draft → registration_open → registration_closed → grouping → ongoing → completed → archived`

### 4.3 Registration `/registration/{seasonId}`

ลำดับการสมัครที่ตรวจพบและควรนำมาใช้:

1. **รายละเอียด** — รูปประชาสัมพันธ์ สถานะ จำนวนที่นั่ง ประเภท กติกา คุณสมบัติ และเงื่อนไข
2. **ผู้สมัคร** — เลือก “ผู้เล่นเดิม” หรือ “ผู้เล่นใหม่”
3. **ชำระเงิน** — เลือกช่องทาง แสดง QR/ข้อมูลรับเงิน แนบสลิป และตรวจสรุปก่อนส่ง

ฟิลด์ผู้เล่นใหม่ที่แนะนำ:

- ชื่อที่ใช้แข่งขัน
- ชื่อเล่นภาษาไทย
- ชื่อ Facebook หรือช่องทางติดต่อ
- URL โปรไฟล์ Facebook
- รูปโปรไฟล์ (ไม่บังคับ)
- คลับที่สังกัด (ไม่บังคับ)
- อีเมลหรือเบอร์โทรสำหรับติดตามใบสมัคร เก็บเป็นข้อมูลส่วนตัว
- Checkbox ยอมรับกติกา นโยบายความเป็นส่วนตัว และการเผยแพร่ชื่อที่ใช้แข่งขัน

สถานะใบสมัคร:

`draft → submitted → payment_review → approved | rejected | waitlisted | withdrawn`

กฎสำคัญ:

- ใช้ transaction หรือ RPC ตอนส่งใบสมัคร เพื่อกันที่นั่งเกินจำนวน
- ป้องกันผู้เล่นเดียวสมัคร Season เดียวซ้ำด้วย unique constraint
- ตรวจชนิด MIME, ขนาดไฟล์ และเปลี่ยนชื่อไฟล์บนเซิร์ฟเวอร์
- สลิปต้องอยู่ใน private bucket และเปิดผ่าน signed URL เฉพาะ Staff
- ห้ามเชื่อยอดเงิน วันที่ หรือเลขอ้างอิงจากฝั่ง browser
- การอนุมัติสลิปเป็นงานของ Staff หรือเชื่อมบริการตรวจสลิปภายหลัง

### 4.4 Records `/records`

- ทำเนียบแชมป์และรองแชมป์ราย Season
- ค้นหาหรือกรองตามปี เวอร์ชันเกม และประเภทการแข่งขัน
- Pagination จากฐานข้อมูล
- เชื่อมไปหน้า Season และโปรไฟล์ผู้เล่น

### 4.5 Player Rankings `/players`

- Top 3 แบบ podium
- ตารางอันดับตั้งแต่อันดับ 4 เป็นต้นไป
- ค้นหาชื่อแข่งขัน ชื่อเล่น หรือ Member ID
- โหมด Current / All-Time / Game Version
- แสดงคะแนน จำนวนแข่ง แชมป์ รองแชมป์ Win Rate และแนวโน้มอันดับ

นิยามที่ต้องกำหนดให้ชัดก่อนพัฒนา:

- Current Ranking ใช้กี่ Season ล่าสุด (เว็บอ้างอิงระบุ 30 Season ที่ปิดแล้ว)
- คะแนนแต่ละรอบ เช่น Champion, Runner-up, Semi-final, Group Stage
- น้ำหนักตามชนิด Season หรือจำนวนผู้เข้าร่วม
- การตัดสินอันดับเมื่อคะแนนเท่ากัน
- วันและเหตุการณ์ที่คำนวณคะแนนใหม่

ควรเก็บกติกาคะแนนแบบมีเวอร์ชันใน `ranking_rules` และเก็บผลคำนวณแยกใน `player_ranking_snapshots` เพื่อให้ตรวจสอบย้อนหลังได้

### 4.6 Club Rankings `/clubs`

- Top 3 และรายการอันดับทั้งหมด
- ค้นหาชื่อเต็มหรือชื่อย่อ
- แสดงเจ้าของ จำนวนสมาชิก จำนวนแชมป์ รองแชมป์ และจำนวนรายการที่ลงแข่ง
- Current Ranking ควรรวมคะแนนของสมาชิกที่สังกัดคลับในช่วงเวลาที่กำหนด

ต้องตัดสินกฎเรื่องการย้ายคลับให้ชัดเจน โดยแนะนำให้เก็บประวัติสมาชิกพร้อม `joined_at` และ `left_at` ไม่แก้ทับข้อมูลเดิม เพื่อคำนวณคะแนนตามช่วงเวลาจริงได้

### 4.7 Hall of Fame `/hall-of-fame`

หมวดที่พบ:

- แชมป์มากที่สุด
- ลงแข่งมากที่สุด
- Win Rate สูงสุด
- รองแชมป์มากที่สุด
- ดาวรุ่ง
- Comeback

แต่ละหมวดควรมีคำอธิบายสูตร เงื่อนไขขั้นต่ำ และวันที่อัปเดต เพื่อให้โปร่งใส

### 4.8 Clips `/clips`

- ค้นหาชื่อคลิปหรือผู้เล่น
- กรองตาม Season
- แสดง YouTube thumbnail, ชื่อ, วันที่ และผู้เล่นที่เกี่ยวข้อง
- เก็บเฉพาะ metadata กับ YouTube URL ไม่ควรดาวน์โหลดวิดีโอมาเก็บเอง

### 4.9 Matchmaking `/matchmaking`

- ค้นหาและเลือกผู้เล่นฝั่งซ้าย/ขวา
- เปรียบเทียบคะแนน ผลงานสะสม แชมป์ Win Rate และฟอร์มล่าสุด
- แสดง Head-to-Head จากผลแข่งขันจริง
- สร้าง Match Graphic สำหรับดาวน์โหลดหรือแชร์

รุ่นแรกสามารถสร้างภาพฝั่ง PHP ด้วย GD/Imagick ส่วนรุ่นถัดไปอาจใช้ HTML Canvas เพื่อ preview ก่อนส่งให้ PHP render ภาพความละเอียดสูง

### 4.10 About และ Contact

- About: ประวัติ เป้าหมาย จุดเด่น และ CTA เข้าร่วมแข่งขัน
- Contact: อีเมลธุรกิจและลิงก์ Community
- Privacy Policy และ Terms ต้องเป็นหน้าจริง ไม่ใช้ลิงก์ `#`

## 5. ระบบหลังบ้านที่เสนอ

### 5.1 Staff Dashboard `/admin`

- KPI: Season ที่เปิดอยู่ ใบสมัครรอตรวจ สลิปรอตรวจ นัดที่ยังไม่บันทึกผล
- Recent Activity และคำเตือนข้อมูลผิดปกติ
- ทางลัดไปงานเร่งด่วน

### 5.2 Tournament Management

- สร้าง/แก้ไข/duplicate Season
- กำหนดชื่อ slug ประเภท เวอร์ชันเกม จำนวนรับ ค่าสมัคร และวันสำคัญ
- จัดการรูปประชาสัมพันธ์หลายรูป
- จัดการกติกา คุณสมบัติ และข้อตกลง
- เปิด/ปิดรับสมัครด้วยสถานะ ไม่ใช้การลบข้อมูล
- จัดกลุ่ม/สายการแข่งขันและสร้างแมตช์
- ปิด Season และเลือก Champion/Runner-up

### 5.3 Application & Payment Review

- คิวใบสมัครตามสถานะ
- ดูผู้สมัคร ประวัติเดิม สลิป และข้อมูลการชำระเงิน
- Approve / Reject / Waitlist พร้อมหมายเหตุ
- บันทึกผู้ตรวจและเวลา
- การเปลี่ยนสถานะทุกครั้งเขียน Audit Log

### 5.4 Player & Club Management

- ค้นหา Merge ผู้เล่นซ้ำ และแก้ข้อมูลที่ได้รับอนุญาต
- Member ID คงที่และอ่านง่าย เช่น `PLR-000123`
- จัดการเจ้าของ/สมาชิกคลับและประวัติการย้าย
- ห้ามลบผู้เล่นที่มีประวัติแข่ง ให้ใช้ `is_active` หรือ soft delete

### 5.5 Match & Result Management

- จัดคู่ แข่งขันหลายรอบ หรือรูปแบบกลุ่ม
- บันทึกคะแนน ผู้ชนะ สถานะ Walkover/Forfeit และหลักฐาน
- รองรับ Best-of-N หากต้องใช้
- เมื่อยืนยันผล ให้ trigger สร้าง activity และคิวคำนวณอันดับใหม่
- การแก้ผลย้อนหลังต้องมีเหตุผลและ Audit Log

### 5.6 Content Management

- คลิป YouTube
- ข่าว/ประกาศ
- ผู้เล่นน่าจับตา
- เนื้อหา About / Contact / Policy
- Social links และ SEO metadata

## 6. สถาปัตยกรรมเทคโนโลยี

### 6.1 Stack ที่แนะนำ

- **Backend:** PHP 8.3+
- **Framework:** Laravel 12 หรือรุ่น LTS/รุ่นเสถียรที่เลือกใช้ในวันเริ่มโครงการ
- **Template:** Blade
- **CSS:** Tailwind CSS หรือ SCSS ที่จัด token กลาง
- **JavaScript:** Alpine.js สำหรับ interaction เล็ก ๆ
- **Database:** Supabase Postgres
- **Auth:** Supabase Auth สำหรับผู้เล่นและ Staff
- **Files:** Supabase Storage
- **Data access:** Supabase REST API จาก PHP หรือเชื่อม Postgres โดยตรงจาก trusted backend
- **Realtime:** ใช้เฉพาะ Activity Feed/ผลสดที่จำเป็น
- **Queue:** Laravel Queue สำหรับคำนวณอันดับ สร้างภาพ และงานแจ้งเตือน
- **Cache:** Laravel Cache; เริ่มจาก database/file และเปลี่ยนเป็น Redis เมื่อมีทราฟฟิก

Supabase สร้าง REST API จาก schema ผ่าน PostgREST และรองรับ RLS จึงเหมาะกับ PHP แม้ไม่ผูกกับ client library เฉพาะภาษา อย่างไรก็ตามงานที่ใช้ `service_role` ต้องทำบน PHP backend เท่านั้น และห้ามส่ง key นี้ไปยัง JavaScript หรือฝังใน repository

### 6.2 โครงสร้างการไหลของข้อมูล

```text
Browser
  │
  ├─ GET หน้า public ───────────────► PHP/Laravel ──► Supabase REST/Postgres
  │
  ├─ POST สมัคร/อัปโหลด ───────────► PHP validation ─► RPC + Storage
  │
  └─ Staff action + JWT ───────────► PHP authorization ─► Supabase + Audit Log
                                                         │
                                                         └─ Realtime/Webhook/Queue
```

สำหรับระบบรุ่นแรก แนะนำให้ browser ติดต่อ PHP เป็นหลัก เพื่อรวม validation, rate limiting, CSRF, logging และ business rules ไว้จุดเดียว แล้วใช้ RLS เป็นชั้นป้องกันเพิ่มเติม

## 7. โครงสร้างฐานข้อมูล Supabase

### 7.1 Identity และสิทธิ์

#### `profiles`

| ฟิลด์ | ชนิด | หมายเหตุ |
|---|---|---|
| id | uuid PK/FK auth.users | รหัสบัญชี |
| display_name | text | ชื่อแสดงผล |
| avatar_path | text nullable | path ใน Storage |
| role | text | user, staff, admin; ควรใช้ app metadata/RBAC ประกอบ |
| status | text | active, suspended |
| created_at, updated_at | timestamptz | เวลา |

#### `staff_permissions`

เก็บสิทธิ์ราย Staff เช่น `tournaments.manage`, `applications.review`, `results.manage`, `content.manage` โดย Admin เท่านั้นที่แก้ได้

### 7.2 ผู้เล่นและคลับ

#### `players`

| ฟิลด์ | ชนิด | หมายเหตุ |
|---|---|---|
| id | uuid PK | รหัสภายใน |
| member_no | bigint unique | เลขสมาชิก |
| user_id | uuid nullable | เชื่อมบัญชีเมื่อ claim โปรไฟล์ |
| competition_name | text | ชื่อใช้แข่ง |
| nickname | text | ชื่อเล่น |
| contact_name | text private | ชื่อช่องทางติดต่อ |
| contact_url | text private | URL ช่องทางติดต่อ |
| avatar_path | text nullable | รูปโปรไฟล์ |
| status | text | active, inactive, banned |
| created_at, updated_at | timestamptz | เวลา |

#### `clubs`

`id, name, short_name, slug, logo_path, owner_player_id, description, status, created_at, updated_at`

#### `club_memberships`

`id, club_id, player_id, role, joined_at, left_at, created_by`

Constraint: ผู้เล่นมี membership ที่ `left_at is null` ได้ไม่เกิน 1 รายการ

### 7.3 การแข่งขัน

#### `seasons`

`id, season_no, slug, title, description, season_type, game_version, status, capacity, registration_open_at, registration_close_at, starts_at, ends_at, entry_fee, payment_config_id, rules_json, published_at, created_by, created_at, updated_at`

#### `season_media`

`id, season_id, storage_path, alt_text, sort_order`

#### `registrations`

`id, season_id, player_id, applicant_user_id, applicant_type, club_id, status, submitted_at, reviewed_at, reviewed_by, rejection_reason, tracking_token_hash, created_at, updated_at`

Unique: `(season_id, player_id)`

#### `payments`

`id, registration_id, method, expected_amount, sender_name, slip_path, status, reference_no, paid_at, reviewed_at, reviewed_by, review_note, created_at`

#### `groups`

`id, season_id, name, sort_order`

#### `group_members`

`id, group_id, registration_id, seed_no`

#### `matches`

`id, season_id, group_id nullable, round_code, bracket_position, home_player_id, away_player_id, home_score, away_score, winner_player_id, status, scheduled_at, played_at, result_note, confirmed_by, created_at, updated_at`

#### `match_evidence`

`id, match_id, storage_path, uploaded_by, created_at`

#### `season_awards`

`id, season_id, award_type, player_id, rank, note`

### 7.4 Ranking และสถิติ

#### `ranking_rules`

`id, name, version, scope, config_json, effective_from, effective_to, is_active`

#### `player_season_stats`

`player_id, season_id, played, won, drawn, lost, goals_for, goals_against, placement, points_awarded`

#### `player_ranking_snapshots`

`id, scope, game_version nullable, player_id, rank, points, calculated_at, rule_id`

#### `club_ranking_snapshots`

`id, scope, club_id, rank, points, member_count, calculated_at, rule_id`

#### `hall_of_fame_entries`

อาจเป็น materialized view หรือ cache table: `category, player_id, value, rank, calculated_at`

### 7.5 Content และระบบ

#### `clips`

`id, season_id nullable, youtube_url, youtube_video_id, title, thumbnail_url, published_at, is_featured, created_by`

#### `clip_players`

`clip_id, player_id`

#### `activities`

`id, type, actor_player_id nullable, season_id nullable, match_id nullable, payload_json, visibility, occurred_at`

#### `pages`

`id, slug, title, content, status, seo_title, seo_description, updated_by, updated_at`

#### `site_settings`

`key, value_json, updated_by, updated_at`

#### `audit_logs`

`id, actor_user_id, action, entity_type, entity_id, before_json, after_json, ip_hash, created_at`

## 8. Views, Functions และ Trigger ที่ควรมี

- `public_player_profiles` — เผยแพร่เฉพาะชื่อแข่ง ชื่อเล่น รูป และสถิติ ไม่รวมข้อมูลติดต่อ
- `current_player_rankings` — อันดับล่าสุดของผู้เล่น
- `current_club_rankings` — อันดับล่าสุดของคลับ
- `season_capacity_summary` — approved + pending + remaining
- `player_head_to_head(player_a, player_b)` — สถิติพบกัน
- `submit_registration(...)` — ตรวจช่วงเวลา ที่นั่ง และข้อมูลซ้ำใน transaction เดียว
- `approve_registration(registration_id)` — เปลี่ยนสถานะและบันทึกผู้ตรวจ
- `finalize_match(match_id, result)` — ยืนยันผลและสร้าง activity
- trigger `updated_at`
- trigger สร้าง activity หลังอนุมัติผู้สมัคร/ยืนยันผล/ปิด Season
- job คำนวณ ranking ใหม่หลังผลเปลี่ยนหรือเมื่อปิด Season

## 9. แนวทาง RLS และ Storage

ตารางทุกตัวใน schema ที่เปิดผ่าน Data API ต้องเปิด RLS

| ข้อมูล | Anonymous | Authenticated owner | Staff/Admin |
|---|---|---|---|
| Season/Ranking/Clips ที่ publish | SELECT | SELECT | CRUD ตามสิทธิ์ |
| Player public fields | ผ่าน view เท่านั้น | ดู/แก้ของตนตาม field ที่อนุญาต | จัดการได้ตามสิทธิ์ |
| Contact information | ห้าม | ดูของตน | ดูเมื่อจำเป็นต่องาน |
| Registration | ห้าม list | ดูของตน/ผ่าน tracking token | ตรวจและเปลี่ยนสถานะ |
| Payment/Slip | ห้าม | ดูสถานะของตน ไม่เปิด slip URL ถาวร | ดูด้วย signed URL |
| Audit log | ห้าม | ห้าม | Admin หรือ auditor |

Bucket ที่แนะนำ:

- `public-assets` — โลโก้ ภาพ Season ภาพหน้าเว็บ อ่านสาธารณะ
- `player-avatars` — รูปโปรไฟล์ที่ผ่านการตรวจ
- `payment-slips` — private, จำกัด JPG/PNG/WebP และ 5 MB
- `match-evidence` — private
- `generated-match-graphics` — public หรือ signed URL ตามนโยบาย

หลักความปลอดภัย:

- `service_role`/secret key อยู่ใน environment ฝั่ง PHP เท่านั้น
- publishable/anon key ใช้ได้เฉพาะกับ policy ที่รัดกุม
- สิทธิ์ Staff ไม่ควรอ่านจาก `user_metadata` ที่ผู้ใช้แก้เอง ให้ใช้ app metadata หรือตารางสิทธิ์ที่แก้ได้เฉพาะ Admin
- signed URL ต้องมีอายุสั้น
- Storage metadata ไม่ควรถูกแก้ตรงด้วย SQL ให้ใช้ Storage API

## 10. PHP Routes และ Service Layer

### Public routes

```text
GET  /
GET  /tournaments
GET  /tournaments/{slug}
GET  /registration/{seasonId}
POST /registration/{seasonId}
GET  /registration/status/{token}
GET  /records
GET  /players
GET  /players/{memberNo}
GET  /clubs
GET  /clubs/{slug}
GET  /hall-of-fame
GET  /clips
GET  /matchmaking
POST /matchmaking/preview
GET  /about
GET  /contact
GET  /privacy
GET  /terms
```

### Admin routes

```text
GET/POST/PATCH /admin/tournaments/*
GET/PATCH      /admin/registrations/*
GET/PATCH      /admin/payments/*
GET/POST/PATCH /admin/players/*
GET/POST/PATCH /admin/clubs/*
GET/POST/PATCH /admin/matches/*
GET/POST/PATCH /admin/clips/*
GET/PATCH      /admin/settings/*
GET            /admin/audit-logs
```

### PHP services

- `SupabaseRestClient`
- `SupabaseStorageClient`
- `AuthService`
- `TournamentService`
- `RegistrationService`
- `PaymentReviewService`
- `RankingService`
- `MatchService`
- `MatchGraphicService`
- `ActivityService`
- `AuditService`

Controllers ควรบาง: รับ request → validate → เรียก service → ส่ง response ส่วน business rules อยู่ใน service/RPC และมี test แยก

## 11. โครงสร้างโปรเจกต์ที่แนะนำ

```text
app/
  Http/Controllers/
    Public/
    Admin/
  Http/Requests/
  Policies/
  Services/
    Supabase/
    Tournament/
    Ranking/
  Jobs/
  ViewModels/
resources/
  views/
    layouts/
    components/
    public/
    admin/
  css/
  js/
routes/
  web.php
  admin.php
supabase/
  migrations/
  seed.sql
tests/
  Feature/
  Unit/
```

## 12. Design System: ดำ–แดง–ขาว

### สีหลัก

| Token | HEX | การใช้งาน |
|---|---|---|
| `--color-bg` | `#090909` | พื้นหลังหลัก |
| `--color-surface` | `#141414` | Card / Navbar |
| `--color-surface-2` | `#1E1E1E` | Hover / Elevated surface |
| `--color-primary` | `#E10600` | CTA, active state, badge สำคัญ |
| `--color-primary-hover` | `#FF241A` | Hover |
| `--color-primary-dark` | `#8F0905` | gradient / border |
| `--color-text` | `#FFFFFF` | ข้อความหลัก |
| `--color-text-muted` | `#B8B8B8` | ข้อความรอง |
| `--color-border` | `#303030` | เส้นแบ่ง |
| `--color-success` | `#22C55E` | อนุมัติ/ชนะ |
| `--color-warning` | `#F59E0B` | รอตรวจ |
| `--color-danger` | `#EF4444` | ปฏิเสธ/ผิดพลาด |

หลักการใช้สี:

- พื้นที่ประมาณ 70% ดำ/เทาเข้ม, 20% ขาว/เทา, 10% แดง
- ใช้แดงเพื่อบอก action และจุดสำคัญ ไม่ใช้เป็นพื้นหลังทุกส่วน
- ข้อความบนแดงต้องผ่าน contrast และควรใช้ขาว
- สถานะไม่ควรสื่อด้วยสีอย่างเดียว ต้องมีข้อความหรือ icon

### ตัวอักษรและองค์ประกอบ

- ภาษาไทย: `Noto Sans Thai` หรือ `IBM Plex Sans Thai`
- Heading: น้ำหนัก 700–800
- Body: น้ำหนัก 400–500, line-height อย่างน้อย 1.6
- Card: radius 12–16px, border เทาเข้ม, shadow บาง
- CTA หลัก: พื้นแดง ตัวอักษรขาว สูงอย่างน้อย 44px
- ตารางบนมือถือเปลี่ยนเป็น card list หรือ scroll แนวนอนพร้อมหัวตารางค้าง
- Ranking Top 3 ใช้ podium แต่ต้องมีรายการแบบข้อความสำหรับ accessibility

### Responsive breakpoints

- Mobile: 360–767px
- Tablet: 768–1023px
- Desktop: 1024–1439px
- Wide: 1440px ขึ้นไป โดย content max-width ราว 1280px

## 13. Validation และกฎธุรกิจ

- ชื่อ Season และ slug ต้อง unique
- `registration_open_at < registration_close_at`
- `capacity > 0`
- ผู้เล่นเดียวสมัคร Season เดียวได้ครั้งเดียว
- ผู้สมัครที่ rejected อาจสมัครใหม่ได้เฉพาะเมื่อ Staff เปิดสิทธิ์
- จำนวนที่นั่งนับ `approved`; อาจกันที่ให้ `payment_review` ชั่วคราวพร้อมหมดอายุ
- Match ต้องมีผู้เล่นต่างกัน
- ผลเสมออนุญาตเฉพาะรูปแบบที่รองรับ
- Winner ต้องเป็นหนึ่งในผู้เล่นของ Match
- Season ปิดได้เมื่อแมตช์ที่จำเป็นเสร็จครบและระบุรางวัลแล้ว
- เปลี่ยนผลที่ยืนยันแล้วต้องใช้สิทธิ์สูงและเขียน audit
- ข้อมูล Ranking ที่แสดงต้องระบุเวลาคำนวณล่าสุด

## 14. ความปลอดภัยและความเป็นส่วนตัว

- ใช้ HTTPS ทุกหน้า
- ใช้ Supabase Auth, MFA สำหรับ Staff/Admin และ session อายุเหมาะสม
- CSRF protection สำหรับฟอร์ม PHP
- Rate limit: login, player search, registration submit, upload และ matchmaking render
- Validate ทั้ง client และ server แต่เชื่อเฉพาะ server
- Escape output ป้องกัน XSS และใช้ Content Security Policy
- จำกัด CORS เฉพาะโดเมนจริง
- ไม่บันทึก token, key, สลิป หรือข้อมูลส่วนตัวลง application log
- Hash tracking token ในฐานข้อมูล
- Malware scan ไฟล์อัปโหลดถ้าโครงการมีความเสี่ยง/ปริมาณสูง
- Backup และทดสอบ restore ตามรอบ
- กำหนด retention: สลิปและข้อมูลติดต่อควรลบเมื่อพ้นความจำเป็นทางบัญชี/การแข่งขัน
- มี Privacy Policy, Terms, consent และช่องทางขอลบ/แก้ข้อมูล
- Staff action ที่กระทบสถานะ เงิน ผลแข่ง หรือคะแนนต้องมี Audit Log

## 15. Performance และ SEO

- ใช้ server-side rendering จาก Blade สำหรับหน้าสาธารณะ
- ทำ index อย่างน้อยที่ `season.status`, `registration.season_id/status`, `matches.season_id/status`, ชื่อค้นหาแบบ normalized และ ranking scope/rank
- ใช้ materialized view/snapshot กับ ranking และ Hall of Fame
- Cache หน้า Dashboard และ ranking ช่วงสั้น พร้อม purge เมื่อข้อมูลเปลี่ยน
- ใช้ pagination แบบ cursor เมื่อข้อมูลมาก
- รูปใช้ WebP/AVIF, responsive sizes และ lazy loading
- Metadata: title, description, canonical, Open Graph และ JSON-LD ประเภท SportsEvent ตามความเหมาะสม
- สร้าง sitemap.xml และ robots.txt
- slug อ่านง่ายและไม่ผูกกับ ID อย่างเดียว

## 16. Accessibility

- รองรับ keyboard ทุก interaction
- Focus state สีขาว/แดงที่มองเห็นชัด
- Form มี label, help text และ error เชื่อมด้วย `aria-describedby`
- Modal login ต้อง trap focus และปิดด้วย Escape
- Carousel ต้องมีปุ่ม Previous/Next พร้อมชื่อที่อ่านรู้เรื่อง
- รูปมี alt text; รูปตกแต่งใช้ alt ว่าง
- ตาราง/อันดับมี heading และลำดับ semantic
- เป้าหมายขั้นต่ำ WCAG 2.2 AA

## 17. Testing

### Unit tests

- สูตรคะแนนทุกเวอร์ชัน
- Tie-breaker
- Season capacity
- สถานะใบสมัครและสถานะแข่งขัน
- Head-to-Head

### Feature tests

- ผู้เล่นใหม่/เดิมสมัครสำเร็จ
- สมัครซ้ำถูกปฏิเสธ
- ที่นั่งสุดท้ายพร้อมคำขอชนกัน
- ไฟล์ผิดชนิด/ใหญ่เกินถูกปฏิเสธ
- Staff อนุมัติสลิปและเกิด audit
- ผู้ใช้ทั่วไปเข้าหน้า admin ไม่ได้
- ข้อมูลติดต่อไม่หลุดใน API public

### End-to-end tests

- Browse Season → Register → Upload → Submit → Staff Approve
- Create Season → Add Players → Record Matches → Complete → Ranking Updated
- Matchmaking → Select 2 Players → Render Graphic
- Mobile menu, search, pagination และ keyboard navigation

## 18. แผนพัฒนาเป็นระยะ

### Phase 0 — Discovery (1 สัปดาห์)

- กำหนดชื่อแบรนด์และเนื้อหาใหม่
- ยืนยันรูปแบบทัวร์นาเมนต์ สูตรคะแนน และกฎคลับ
- ทำ data inventory และ privacy requirements
- ทำ wireframe mobile/desktop

### Phase 1 — Foundation (1–2 สัปดาห์)

- ตั้ง Laravel, environment และ CI
- สร้าง Supabase project, migration, Storage buckets และ RLS
- Auth/RBAC, layout, design tokens และ component พื้นฐาน

### Phase 2 — Tournament & Registration MVP (2–3 สัปดาห์)

- Season list/detail
- ผู้เล่นและคลับ
- ฟอร์มสมัคร 3 ขั้นตอน
- Private slip upload
- Staff review และ audit

### Phase 3 — Results & Rankings (2–3 สัปดาห์)

- กลุ่ม/สาย/แมตช์
- บันทึกและยืนยันผล
- Ranking, Club Ranking, Records และ Hall of Fame

### Phase 4 — Content & Community (1–2 สัปดาห์)

- Dashboard activity
- Clips, About, Contact และ Policy
- Matchmaking และ Match Graphic

### Phase 5 — Hardening & Launch (1–2 สัปดาห์)

- Security review, performance, accessibility และ SEO
- E2E/UAT
- backup/restore drill
- monitoring, error alert และ launch checklist

เวลารวมโดยประมาณ 7–11 สัปดาห์สำหรับทีมขนาดเล็ก ขึ้นกับความซับซ้อนของรูปแบบการแข่งขันและการย้ายข้อมูลเดิม

## 19. MVP ที่ควรปล่อยก่อน

MVP ควรมี:

- Dashboard
- Tournaments + Detail
- Player/Club directory
- Registration + Slip upload
- Staff login + Review queue
- Match/result management ขั้นพื้นฐาน
- Player/Club ranking
- Privacy, Terms และ Audit Log

สิ่งที่เลื่อนไปรุ่นถัดไปได้:

- Realtime ผลสดเต็มรูปแบบ
- Match Graphic ขั้นสูง
- ระบบเจ้าของคลับแบบ self-service
- การตรวจสลิปอัตโนมัติ
- Notifications หลายช่องทาง
- Advanced analytics

## 20. Definition of Done

ระบบพร้อมเปิดใช้งานเมื่อ:

- ผู้ใช้สมัครรายการได้ครบทั้งผู้เล่นใหม่และเดิม
- ไม่มีการสมัครซ้ำหรือจำนวนอนุมัติเกิน capacity แม้มีคำขอพร้อมกัน
- สลิปและข้อมูลติดต่อไม่เข้าถึงจาก public URL
- Staff ตรวจ/อนุมัติได้และทุก action สำคัญมี audit
- ผลแข่งเชื่อม Season/Player ถูกต้องและย้อนตรวจได้
- Ranking ให้ผลตรงกับชุดทดสอบสูตรคะแนน
- หน้าหลักใช้งานได้ที่ 360px, 768px, 1024px และ desktop
- ผ่าน accessibility smoke test และไม่มี critical security finding
- backup และ restore ถูกทดสอบจริง
- มี monitoring และคู่มือแก้เหตุขัดข้องเบื้องต้น

## 21. คำถามที่ต้องยืนยันก่อนเริ่มเขียนระบบ

1. ต้องการให้ผู้สมัครสร้างบัญชีหรือสมัครแบบ guest?
2. รูปแบบการแข่งขันมี Group, Knockout, League และ Best-of-N แบบใดบ้าง?
3. สูตร BCL Point/คะแนนใหม่เป็นอย่างไร และ Current Ranking ใช้กี่ Season?
4. คะแนนคลับยึดสังกัด ณ วันแข่ง วันปิด Season หรือสังกัดปัจจุบัน?
5. ช่องทางชำระเงินมีอะไรบ้าง และต้องการตรวจสลิปอัตโนมัติหรือไม่?
6. ต้องย้ายข้อมูลผู้เล่น/ผลการแข่งขันเก่าหรือเริ่มฐานข้อมูลใหม่?
7. Staff แบ่งหน้าที่และสิทธิ์ละเอียดระดับใด?
8. ต้องการภาษาไทยอย่างเดียวหรือไทย/อังกฤษ?
9. จะ deploy ที่ shared hosting, VPS หรือ platform ที่รองรับ Laravel?
10. ระยะเวลาเก็บสลิป ข้อมูลติดต่อ และ Audit Log เท่าใด?

## 22. แหล่งอ้างอิงทางเทคนิค

- เว็บไซต์ที่ใช้ศึกษารูปแบบฟังก์ชัน: https://bomzaghi4.com/
- Supabase Data REST API: https://supabase.com/docs/guides/api
- Supabase Row Level Security: https://supabase.com/docs/guides/database/postgres/row-level-security
- Supabase Secure Data: https://supabase.com/docs/guides/database/secure-data
- Supabase Storage Access Control: https://supabase.com/docs/guides/storage/security/access-control
- Supabase Storage Schema: https://supabase.com/docs/guides/storage/schema/design
- Supabase Realtime: https://supabase.com/docs/guides/realtime/subscribing-to-database-changes

