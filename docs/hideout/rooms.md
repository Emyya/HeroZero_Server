# HIDEOUT — BUILDINGS / ROOMS (assets/hideout_rooms)

## Data origin

This document describes all the hideout rooms/buildings based on the **image metadata** (folder `assets/hideout_rooms`) and the real game data in `constants_json.data` (key `hideout_rooms`).

### Image naming convention

- `<building>_<stage>.webp` — the building's appearance at that **stage** (appearance level / `image_level`).

- `<building>_i.webp` — building icon in the panels (not a stage).

- The building level (1..50) raises the effect; the appearance images go up to stage 20 at most.


### Field legend (per stage/level)

- **Stone / Glue / Gold**: cost to build/upgrade to that level.

- **Build time**: construction/upgrade time.

- **Resource cap** (`resource_production_max`): stored amount (capacity).

- **Fill time** (`min_till_max_resource`): seconds to reach full capacity.

- **Bonus 1 / Bonus 2** (`passiv_bonus_amount_1/2`): the passive bonus of that room type (varies by type).

- **Unlock**: for `main_building` it is 0 = always available; for the others it is the main building level needed to build them.


## main_building — type: main_building

- **Build limit:** 1 | **Size (cells):** 3 | **Levels:** 1 to 50 (50)
- **Unlock:** always available (main building level 0).
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Main building. Produces stone and glue over time; every level raises production and speed.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 2 | 1 | 0 | 1 | 1 | 480 | 100 | 200 |
| 2 | 5 | 2 | 0 | 1 | 1.1 | 480 | 200 | 400 |
| 3 | 10 | 5 | 0 | 1 | 1.2 | 480 | 500 | 1000 |
| 5 | 2580 | 1200 | 0 | 19 | 1.4 | 480 | 3200 | 6400 |
| 10 | 76030 | 38020 | 0 | 360 | 1.9 | 480 | 60600 | 121200 |
| 15 | 550180 | 275090 | 0 | 7200 | 2.4 | 480 | 377000 | 754000 |
| 20 | 2240410 | 1120200 | 0 | 128160 | 2.9 | 480 | 1421500 | 2843000 |
| 25 | 6658020 | 3329010 | 0 | 205920 | 3.4 | 480 | 4031400 | 8062800 |
| 30 | 16211710 | 8105860 | 0 | 214560 | 3.9 | 480 | 9512800 | 19025600 |
| 40 | 66016850 | 33008430 | 0 | 234720 | 4.9 | 480 | 37236500 | 74473000 |
| 50 | 196188070 | 98094030 | 0 | 256320 | 5.9 | 480 | 108048900 | 216097800 |

## stone_production — type: resource_production

- **Build limit:** 6 | **Size (cells):** 1 | **Levels:** 1 to 50 (50)
- **Unlocks at main building level:** [2, 5, 11, 20, 30, 40]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Pure resource production. Stores resource up to `resource_production_max` and fills it in `min_till_max_resource` seconds.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 0 | 5 | 0 | 1 | 300 | 15 | 0 | 0 |
| 2 | 0 | 10 | 100 | 3 | 640 | 30 | 0 | 0 |
| 3 | 0 | 110 | 700 | 6 | 1320 | 60 | 0 | 0 |
| 5 | 0 | 1290 | 7000 | 19 | 2880 | 120 | 0 | 0 |
| 10 | 0 | 38020 | 158100 | 360 | 7840 | 270 | 0 | 0 |
| 15 | 0 | 275090 | 980300 | 7200 | 14280 | 420 | 0 | 0 |
| 20 | 0 | 1120200 | 3577700 | 128160 | 22240 | 570 | 0 | 0 |
| 25 | 0 | 3329010 | 9765600 | 205920 | 31680 | 720 | 0 | 0 |
| 30 | 0 | 8105860 | 22182800 | 214560 | 42640 | 870 | 0 | 0 |
| 40 | 0 | 33008430 | 80954300 | 234720 | 69040 | 1170 | 0 | 0 |
| 50 | 0 | 98094030 | 220970900 | 256320 | 101440 | 1470 | 0 | 0 |

## glue_production — type: resource_production

- **Build limit:** 6 | **Size (cells):** 1 | **Levels:** 1 to 50 (50)
- **Unlocks at main building level:** [2, 5, 11, 20, 30, 40]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Pure resource production. Stores resource up to `resource_production_max` and fills it in `min_till_max_resource` seconds.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 10 | 0 | 0 | 1 | 150 | 15 | 0 | 0 |
| 2 | 30 | 0 | 100 | 3 | 320 | 30 | 0 | 0 |
| 3 | 210 | 0 | 700 | 6 | 660 | 60 | 0 | 0 |
| 5 | 2580 | 0 | 7000 | 19 | 1440 | 120 | 0 | 0 |
| 10 | 76030 | 0 | 158100 | 360 | 3920 | 270 | 0 | 0 |
| 15 | 550180 | 0 | 980300 | 7200 | 7140 | 420 | 0 | 0 |
| 20 | 2240410 | 0 | 3577700 | 128160 | 11120 | 570 | 0 | 0 |
| 25 | 6658020 | 0 | 9765600 | 205920 | 15840 | 720 | 0 | 0 |
| 30 | 16211710 | 0 | 22182800 | 214560 | 21320 | 870 | 0 | 0 |
| 40 | 66016850 | 0 | 80954300 | 234720 | 34520 | 1170 | 0 | 0 |
| 50 | 196188070 | 0 | 220970900 | 256320 | 50720 | 1470 | 0 | 0 |

## wall — type: battle

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 50 (50)
- **Unlocks at main building level:** [3]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Battle room. Provides attack/defense units, troops or walls for the hideout.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 20 | 0 | 200 | 1 | 0 | 0 | 2 | 0 |
| 2 | 30 | 10 | 4500 | 3 | 0 | 0 | 3 | 0 |
| 3 | 200 | 50 | 28100 | 6 | 0 | 0 | 4 | 0 |
| 5 | 2450 | 650 | 279500 | 19 | 0 | 0 | 6 | 0 |
| 10 | 72230 | 19010 | 6324600 | 360 | 0 | 0 | 11 | 0 |
| 15 | 522670 | 137540 | 39214000 | 7200 | 0 | 0 | 16 | 0 |
| 20 | 2128390 | 560100 | 143108400 | 128160 | 0 | 0 | 21 | 0 |
| 25 | 6325120 | 1664510 | 390625000 | 205920 | 0 | 0 | 26 | 0 |
| 30 | 15401130 | 4052930 | 498547485 | 214560 | 0 | 0 | 31 | 0 |
| 40 | 62716010 | 16504210 | 812081320 | 234720 | 0 | 0 | 41 | 0 |
| 50 | 186378660 | 49047020 | 1322794898 | 256320 | 0 | 0 | 51 | 0 |

## attacker_production — type: battle

- **Build limit:** 2 | **Size (cells):** 1 | **Levels:** 1 to 50 (50)
- **Unlocks at main building level:** [2, 7]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Battle room. Provides attack/defense units, troops or walls for the hideout.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 20 | 10 | 0 | 1 | 1 | 0 | 1 | 0 |
| 2 | 30 | 20 | 200 | 3 | 2 | 0 | 2 | 0 |
| 3 | 190 | 30 | 1400 | 6 | 3 | 0 | 3 | 0 |
| 5 | 2320 | 320 | 14000 | 19 | 5 | 0 | 5 | 0 |
| 10 | 68430 | 9500 | 316200 | 360 | 10 | 0 | 10 | 0 |
| 15 | 495160 | 68770 | 1960700 | 7200 | 15 | 0 | 15 | 0 |
| 20 | 2016370 | 280050 | 7155400 | 128160 | 20 | 0 | 20 | 0 |
| 25 | 5992220 | 832250 | 19531300 | 205920 | 25 | 0 | 25 | 0 |
| 30 | 14590540 | 2026460 | 44365500 | 214560 | 30 | 0 | 30 | 0 |
| 40 | 59415170 | 8252110 | 161908600 | 234720 | 40 | 0 | 40 | 0 |
| 50 | 176569260 | 24523510 | 441941700 | 256320 | 50 | 0 | 50 | 0 |

## barracks — type: battle

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 50 (50)
- **Unlocks at main building level:** [7]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Battle room. Provides attack/defense units, troops or walls for the hideout.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 10 | 0 | 100 | 1 | 0 | 0 | 2 | 0 |
| 2 | 30 | 0 | 1100 | 3 | 0 | 0 | 3 | 0 |
| 3 | 200 | 30 | 7000 | 6 | 0 | 0 | 4 | 0 |
| 5 | 2450 | 390 | 69900 | 19 | 0 | 0 | 6 | 0 |
| 10 | 72230 | 11400 | 1581100 | 360 | 0 | 0 | 11 | 0 |
| 15 | 522670 | 82530 | 9803500 | 7200 | 0 | 0 | 16 | 0 |
| 20 | 2128390 | 336060 | 35777100 | 128160 | 0 | 0 | 21 | 0 |
| 25 | 6325120 | 998700 | 97656200 | 205920 | 0 | 0 | 26 | 0 |
| 30 | 15401130 | 2431760 | 172103590 | 214560 | 0 | 0 | 31 | 0 |
| 40 | 62716010 | 9902530 | 534527628 | 234720 | 0 | 0 | 41 | 0 |
| 50 | 186378660 | 29428210 | 1660161676 | 256320 | 0 | 0 | 51 | 0 |

## defender_production — type: battle

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 50 (50)
- **Unlocks at main building level:** [7]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Battle room. Provides attack/defense units, troops or walls for the hideout.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 10 | 0 | 0 | 1 | 1 | 0 | 1 | 0 |
| 2 | 30 | 10 | 200 | 3 | 2 | 0 | 2 | 0 |
| 3 | 200 | 40 | 1400 | 6 | 3 | 0 | 3 | 0 |
| 5 | 2450 | 520 | 14000 | 19 | 5 | 0 | 5 | 0 |
| 10 | 72230 | 15210 | 316200 | 360 | 10 | 0 | 10 | 0 |
| 15 | 522670 | 110040 | 1960700 | 7200 | 15 | 0 | 15 | 0 |
| 20 | 2128390 | 448080 | 7155400 | 128160 | 20 | 0 | 20 | 0 |
| 25 | 6325120 | 1331600 | 19531300 | 205920 | 25 | 0 | 25 | 0 |
| 30 | 15401130 | 3242340 | 44365500 | 214560 | 30 | 0 | 30 | 0 |
| 40 | 62716010 | 13203370 | 161908600 | 234720 | 40 | 0 | 40 | 0 |
| 50 | 186378660 | 39237610 | 441941700 | 256320 | 50 | 0 | 50 | 0 |

## worker_home — type: boost

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 20 (20)
- **Unlocks at main building level:** [4]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 30 | 0 | 100 | 2 | 0 | 0 | 5 | 0 |
| 2 | 40 | 10 | 1400 | 3 | 0 | 0 | 10 | 0 |
| 3 | 50 | 100 | 8400 | 6 | 0 | 0 | 15 | 0 |
| 5 | 650 | 1230 | 83900 | 19 | 0 | 0 | 25 | 0 |
| 10 | 19010 | 36120 | 1897400 | 360 | 0 | 0 | 50 | 0 |
| 15 | 137540 | 261330 | 11764200 | 7200 | 0 | 0 | 70 | 0 |
| 20 | 560100 | 1064190 | 42932500 | 128160 | 0 | 0 | 90 | 0 |

## generator — type: boost

- **Build limit:** 9 | **Size (cells):** 1 | **Levels:** 1 to 20 (20)
- **Unlocks at main building level:** [5, 6, 8, 12, 16, 20, 30, 40, 45]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 30 | 0 | 100 | 2 | 0 | 0 | 5 | 0 |
| 2 | 40 | 10 | 1100 | 3 | 0 | 0 | 10 | 0 |
| 3 | 50 | 100 | 7000 | 6 | 0 | 0 | 15 | 0 |
| 5 | 650 | 1160 | 69900 | 19 | 0 | 0 | 25 | 0 |
| 10 | 19010 | 34210 | 1581100 | 360 | 0 | 0 | 50 | 0 |
| 15 | 137540 | 247580 | 9803500 | 7200 | 0 | 0 | 75 | 0 |
| 20 | 560100 | 1008180 | 35777100 | 128160 | 0 | 0 | 100 | 0 |

## xp_production — type: collect

- **Build limit:** 2 | **Size (cells):** 2 | **Levels:** 1 to 50 (50)
- **Unlocks at main building level:** [6, 10]
- **Visual stages:** appearance images up to stage 20 + icon (`_i`).
- **What it does:** Collector room. Generates a resource/item (XP, gem) that must be collected.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 30 | 0 | 0 | 2 | 80 | 15 | 0 | 0 |
| 2 | 30 | 10 | 700 | 3 | 160 | 30 | 0 | 0 |
| 3 | 40 | 100 | 4200 | 6 | 340 | 60 | 0 | 0 |
| 5 | 520 | 1230 | 41900 | 19 | 740 | 120 | 0 | 0 |
| 10 | 15210 | 36120 | 948700 | 360 | 2060 | 270 | 0 | 0 |
| 15 | 110040 | 261330 | 5882100 | 7200 | 3810 | 420 | 0 | 0 |
| 20 | 448080 | 1064190 | 21466300 | 128160 | 5990 | 570 | 0 | 0 |
| 25 | 1331600 | 3162560 | 58593700 | 205920 | 8600 | 720 | 0 | 0 |
| 30 | 3242340 | 7700560 | 133096600 | 214560 | 11640 | 870 | 0 | 0 |
| 40 | 13203370 | 31358010 | 485725800 | 234720 | 19010 | 1170 | 0 | 0 |
| 50 | 39237610 | 93189330 | 1325825200 | 256320 | 28110 | 1470 | 0 | 0 |

## gem_production — type: upgrade

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 50 (50)
- **Unlocks at main building level:** [10]
- **Visual stages:** appearance images up to stage 16 + icon (`_i`).
- **What it does:** Upgrade room. Unlocks or improves mechanics (trade, forge, gym, etc.).

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 30 | 0 | 100 | 2 | 1 | 100 | 5 | 0 |
| 2 | 30 | 10 | 2300 | 3 | 1 | 100 | 10 | 0 |
| 3 | 210 | 110 | 14000 | 6 | 1 | 100 | 15 | 0 |
| 5 | 2580 | 1290 | 139800 | 19 | 1 | 100 | 25 | 0 |
| 10 | 76030 | 38020 | 3162300 | 360 | 1 | 100 | 50 | 0 |
| 15 | 550180 | 275090 | 19607000 | 7200 | 1 | 100 | 75 | 0 |
| 20 | 2240410 | 1120200 | 71554200 | 128160 | 1 | 100 | 100 | 0 |
| 25 | 6658020 | 3329010 | 195312500 | 205920 | 1 | 100 | 115 | 0 |
| 30 | 16211710 | 8105860 | 286978140 | 214560 | 1 | 100 | 125 | 0 |
| 40 | 66016850 | 33008430 | 619564278 | 234720 | 1 | 100 | 145 | 0 |
| 50 | 196188070 | 98094030 | 1337592808 | 256320 | 1 | 100 | 165 | 0 |

## broker — type: boost

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 10 (10)
- **Unlocks at main building level:** [4]
- **Visual stages:** appearance images up to stage 9 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 20 | 10 | 0 | 4320 | 0 | 0 | 33 | 0 |
| 2 | 40 | 20 | 0 | 5760 | 0 | 0 | 55 | 0 |
| 3 | 200 | 100 | 0 | 7200 | 0 | 0 | 70 | 0 |
| 5 | 2500 | 1200 | 0 | 11520 | 0 | 0 | 87 | 0 |
| 10 | 75000 | 37000 | 0 | 31680 | 0 | 0 | 100 | 0 |

## robot_storage — type: battle

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 20 (20)
- **Unlocks at main building level:** [8]
- **Visual stages:** appearance images up to stage 9 + icon (`_i`).
- **What it does:** Battle room. Provides attack/defense units, troops or walls for the hideout.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 20 | 10 | 0 | 4320 | 0 | 0 | 100 | 0 |
| 2 | 30 | 10 | 2300 | 5760 | 0 | 0 | 200 | 0 |
| 3 | 40 | 100 | 14000 | 7200 | 0 | 0 | 300 | 0 |
| 5 | 520 | 1230 | 139800 | 11520 | 0 | 0 | 500 | 0 |
| 10 | 15210 | 36120 | 3162300 | 31680 | 0 | 0 | 1000 | 0 |
| 15 | 110040 | 261330 | 110913800 | 87840 | 0 | 0 | 1500 | 0 |
| 20 | 448080 | 1064190 | 1104854300 | 241920 | 0 | 0 | 2000 | 0 |

## personal_arena — type: boost

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 25 (25)
- **Unlocks at main building level:** [9]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 20 | 10 | 0 | 2880 | 0 | 0 | 5 | 2 |
| 2 | 30 | 10 | 15000 | 5760 | 0 | 0 | 10 | 4 |
| 3 | 40 | 100 | 46000 | 8640 | 0 | 0 | 15 | 6 |
| 5 | 520 | 1230 | 412000 | 14400 | 0 | 0 | 25 | 10 |
| 10 | 15210 | 36120 | 100000000 | 28800 | 0 | 0 | 50 | 20 |
| 15 | 110040 | 261330 | 372000000 | 86400 | 0 | 0 | 75 | 30 |
| 20 | 448080 | 1064190 | 1382000000 | 211680 | 0 | 0 | 100 | 40 |
| 25 | 6658020 | 3329010 | 5132000000 | 220320 | 0 | 0 | 125 | 50 |

## exchange_room — type: upgrade

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 20 (20)
- **Unlocks at main building level:** [11]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Upgrade room. Unlocks or improves mechanics (trade, forge, gym, etc.).

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 2 | 1 | 0 | 1440 | 0 | 0 | 0 | 0 |
| 2 | 5 | 2 | 4500 | 2880 | 0 | 0 | 0 | 0 |
| 3 | 10 | 5 | 28100 | 4320 | 0 | 0 | 0 | 0 |
| 5 | 2580 | 1200 | 279500 | 7200 | 0 | 0 | 0 | 0 |
| 10 | 76030 | 38020 | 6324600 | 14400 | 0 | 0 | 0 | 0 |
| 15 | 550180 | 275090 | 39214000 | 21600 | 0 | 0 | 0 | 0 |
| 20 | 2240410 | 1120200 | 143108400 | 28800 | 0 | 0 | 0 | 0 |

## gym — type: upgrade

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 30 (30)
- **Unlocks at main building level:** [12]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Upgrade room. Unlocks or improves mechanics (trade, forge, gym, etc.).

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 2 | 1 | 0 | 1440 | 0 | 0 | 20 | 0 |
| 2 | 30 | 10 | 4500 | 2880 | 0 | 0 | 21 | 0 |
| 3 | 40 | 100 | 28100 | 4320 | 0 | 0 | 22 | 0 |
| 5 | 520 | 1230 | 279500 | 7200 | 0 | 0 | 24 | 0 |
| 10 | 15210 | 36120 | 6324600 | 14400 | 0 | 0 | 29 | 0 |
| 15 | 110040 | 261330 | 39214000 | 21600 | 0 | 0 | 34 | 0 |
| 20 | 448080 | 1064190 | 143108400 | 28800 | 0 | 0 | 39 | 0 |
| 25 | 6658020 | 3329010 | 1092000000 | 224640 | 0 | 0 | 44 | 0 |
| 30 | 11719781 | 5859899 | 8294000000 | 234720 | 0 | 0 | 49 | 0 |

## kybernetic_improvement — type: boost

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 25 (25)
- **Unlocks at main building level:** [13]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 20 | 10 | 0 | 2880 | 0 | 0 | 1 | 3 |
| 2 | 30 | 10 | 15000 | 5760 | 0 | 0 | 2 | 6 |
| 3 | 40 | 100 | 46000 | 8640 | 0 | 0 | 3 | 9 |
| 5 | 520 | 1230 | 412000 | 14400 | 0 | 0 | 5 | 15 |
| 10 | 15210 | 36120 | 100000000 | 28800 | 0 | 0 | 10 | 30 |
| 15 | 110040 | 261330 | 250000000 | 60480 | 0 | 0 | 15 | 45 |
| 20 | 448080 | 1064190 | 622000000 | 126720 | 0 | 0 | 20 | 60 |
| 25 | 6658020 | 3329010 | 1547000000 | 260640 | 0 | 0 | 25 | 75 |

## sports_bar — type: boost

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 30 (30)
- **Unlocks at main building level:** [8]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 30 | 0 | 0 | 2880 | 0 | 0 | 1 | 0 |
| 2 | 30 | 10 | 700 | 5760 | 0 | 0 | 2 | 0 |
| 3 | 40 | 100 | 4200 | 8640 | 0 | 0 | 3 | 0 |
| 5 | 520 | 1230 | 41900 | 14400 | 0 | 0 | 5 | 0 |
| 10 | 15210 | 36120 | 948700 | 28800 | 0 | 0 | 10 | 0 |
| 15 | 110040 | 261330 | 5882100 | 43200 | 0 | 0 | 15 | 0 |
| 20 | 448080 | 1064190 | 21466300 | 57600 | 0 | 0 | 20 | 0 |
| 25 | 6658020 | 3329010 | 195312500 | 72000 | 0 | 0 | 25 | 0 |
| 30 | 11719781 | 5859899 | 335498600 | 86400 | 0 | 0 | 30 | 0 |

## brain — type: boost

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 25 (25)
- **Unlocks at main building level:** [11]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 30 | 0 | 0 | 2880 | 0 | 0 | 2 | 0 |
| 2 | 30 | 10 | 700 | 5760 | 0 | 0 | 4 | 0 |
| 3 | 40 | 100 | 4200 | 8640 | 0 | 0 | 6 | 0 |
| 5 | 520 | 1230 | 41900 | 14400 | 0 | 0 | 10 | 0 |
| 10 | 15210 | 36120 | 948700 | 28800 | 0 | 0 | 20 | 0 |
| 15 | 110040 | 261330 | 5882100 | 43200 | 0 | 0 | 30 | 0 |
| 20 | 448080 | 1064190 | 21466300 | 57600 | 0 | 0 | 40 | 0 |
| 25 | 6658020 | 3329010 | 195312500 | 72000 | 0 | 0 | 50 | 0 |

## scout_center — type: boost

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 30 (30)
- **Unlocks at main building level:** [9]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 30 | 0 | 0 | 2880 | 0 | 0 | 1 | 0 |
| 2 | 30 | 10 | 700 | 5760 | 0 | 0 | 2 | 0 |
| 3 | 40 | 100 | 4200 | 8640 | 0 | 0 | 3 | 0 |
| 5 | 520 | 1230 | 41900 | 14400 | 0 | 0 | 5 | 0 |
| 10 | 15210 | 36120 | 948700 | 28800 | 0 | 0 | 10 | 0 |
| 15 | 110040 | 261330 | 5882100 | 43200 | 0 | 0 | 15 | 0 |
| 20 | 448080 | 1064190 | 21466300 | 57600 | 0 | 0 | 20 | 0 |
| 25 | 6658020 | 3329010 | 195312500 | 72000 | 0 | 0 | 25 | 0 |
| 30 | 11253737 | 5626871 | 323739380 | 86400 | 0 | 0 | 30 | 0 |

## trophy_room — type: boost

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 25 (25)
- **Unlocks at main building level:** [12]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Passive booster room. Gives a constant percentage/aditive bonus.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 30 | 0 | 0 | 2880 | 0 | 0 | 1 | 0 |
| 2 | 30 | 10 | 700 | 5760 | 0 | 0 | 2 | 0 |
| 3 | 40 | 100 | 4200 | 8640 | 0 | 0 | 3 | 0 |
| 5 | 520 | 1230 | 41900 | 14400 | 0 | 0 | 5 | 0 |
| 10 | 15210 | 36120 | 948700 | 28800 | 0 | 0 | 10 | 0 |
| 15 | 110040 | 261330 | 5882100 | 43200 | 0 | 0 | 15 | 0 |
| 20 | 448080 | 1064190 | 21466300 | 57600 | 0 | 0 | 20 | 0 |
| 25 | 6658020 | 3329010 | 195312500 | 72000 | 0 | 0 | 25 | 0 |

## blacksmith — type: upgrade

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 10 (10)
- **Unlocks at main building level:** [3]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Upgrade room. Unlocks or improves mechanics (trade, forge, gym, etc.).

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 1000 | 500 | 0 | 480 | 0 | 480 | 0 | 0 |
| 2 | 2000 | 1000 | 115900 | 960 | 0 | 470 | 0 | 0 |
| 3 | 4000 | 2000 | 1029800 | 1440 | 0 | 460 | 0 | 0 |
| 5 | 272770 | 136385 | 13420700 | 14400 | 0 | 440 | 0 | 0 |
| 10 | 8888270 | 4444135 | 367777800 | 28800 | 0 | 390 | 0 | 0 |

## guild_main_building — type: guild_main_building

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 1 (1)
- **Unlocks at main building level:** [5]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Main building of the clan (guild) part of the hideout.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 2580 | 1200 | 0 | 20 | 0 | 0 | 0 | 0 |

## guild_auto_join_production — type: upgrade

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 10 (10)
- **Unlocks at main building level:** [5]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Upgrade room. Unlocks or improves mechanics (trade, forge, gym, etc.).

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 23994 | 12006 | 0 | 3456 | 1 | 0 | 0 | 1 |
| 2 | 46044 | 23022 | 9180 | 4608 | 1 | 0 | 5 | 1 |
| 3 | 81828 | 40914 | 25200 | 5760 | 2 | 0 | 10 | 2 |
| 5 | 217926 | 108972 | 114300 | 9216 | 4 | 0 | 20 | 4 |
| 10 | 1357002 | 678510 | 1292940 | 25344 | 10 | 0 | 45 | 10 |

## guild_missile_production — type: upgrade

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 10 (10)
- **Unlocks at main building level:** [5]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Upgrade room. Unlocks or improves mechanics (trade, forge, gym, etc.).

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 25327 | 12673 | 0 | 3888 | 1 | 0 | 3 | 0 |
| 2 | 48602 | 24301 | 9690 | 5184 | 1 | 0 | 4 | 0 |
| 3 | 86374 | 43187 | 26600 | 6480 | 1 | 0 | 6 | 0 |
| 5 | 230033 | 115026 | 120650 | 10368 | 2 | 0 | 8 | 0 |
| 10 | 1432391 | 716205 | 1364770 | 28512 | 6 | 0 | 21 | 0 |

## guild_weapon_oil_production — type: upgrade

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 10 (10)
- **Unlocks at main building level:** [5]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Upgrade room. Unlocks or improves mechanics (trade, forge, gym, etc.).

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 26660 | 13340 | 0 | 4320 | 1 | 0 | 3 | 0 |
| 2 | 51160 | 25580 | 10200 | 5760 | 1 | 0 | 4 | 0 |
| 3 | 90920 | 45460 | 28000 | 7200 | 1 | 0 | 6 | 0 |
| 5 | 242140 | 121080 | 127000 | 11520 | 2 | 0 | 8 | 0 |
| 10 | 1507780 | 753900 | 1436600 | 31680 | 6 | 0 | 21 | 0 |

## guild_medikit_production — type: upgrade

- **Build limit:** 1 | **Size (cells):** 2 | **Levels:** 1 to 10 (10)
- **Unlocks at main building level:** [5]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Upgrade room. Unlocks or improves mechanics (trade, forge, gym, etc.).

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 27993 | 14007 | 0 | 4752 | 1 | 0 | 3 | 0 |
| 2 | 53718 | 26859 | 10710 | 6336 | 1 | 0 | 4 | 0 |
| 3 | 95466 | 47733 | 29400 | 7920 | 1 | 0 | 6 | 0 |
| 5 | 254247 | 127134 | 133350 | 12672 | 2 | 0 | 8 | 0 |
| 10 | 1583169 | 791595 | 1508430 | 34848 | 6 | 0 | 21 | 0 |

## guild_competition_point_production — type: collect

- **Build limit:** 1 | **Size (cells):** 1 | **Levels:** 1 to 10 (10)
- **Unlocks at main building level:** [5]
- **Visual stages:** appearance images up to stage 1 + icon (`_i`).
- **What it does:** Collector room. Generates a resource/item (XP, gem) that must be collected.

| Level | Stone | Glue | Gold | Build time | Resource cap | Fill time | Bonus 1 | Bonus 2 |
|---|---|---|---|---|---|---|---|
| 1 | 29326 | 14674 | 0 | 5184 | 5 | 15 | 0 | 0 |
| 2 | 56276 | 28138 | 11220 | 6912 | 10 | 30 | 0 | 0 |
| 3 | 100012 | 50006 | 30800 | 8640 | 20 | 60 | 0 | 0 |
| 5 | 266354 | 133188 | 139700 | 13824 | 40 | 120 | 0 | 0 |
| 10 | 1658558 | 829290 | 1580260 | 38016 | 120 | 360 | 0 | 0 |