<?php

declare(strict_types=1);

/**
 * Pet daily feeding calculator — mirrors food.xlsx logic:
 *   Weight (kg) = lbs / 2.205 OR direct kg input
 *   RER = 70 × weight^0.75
 *   MER = lifestyle factor × RER
 *   Daily cups = MER / kcal per cup
 *   Per-meal amounts rounded to nearest ⅛ cup
 */

const LIFESTYLE_FACTORS = [
    'adult_intact'     => ['label' => 'Adult Intact',     'dog' => 1.8, 'cat' => 1.4],
    'neutered_spayed'  => ['label' => 'Neutered/Spayed',  'dog' => 1.6, 'cat' => 1.2],
    'senior'           => ['label' => 'Senior',           'dog' => 1.4, 'cat' => 1.1],
    'obese_prone'      => ['label' => 'Obese Prone',      'dog' => 1.3, 'cat' => 1.1],
    'weight_loss'      => ['label' => 'Weight Loss',      'dog' => 1.0, 'cat' => 0.9],
    'growth'           => ['label' => 'Growth',           'dog' => 2.5, 'cat' => 3.5],
    'gestation'        => ['label' => 'Gestation',        'dog' => 2.0, 'cat' => 1.8],
    'lactation'        => ['label' => 'Lactation',        'dog' => 6.0, 'cat' => 4.0],
];

function mround(float $value, float $multiple): float
{
    if ($multiple == 0.0) {
        return 0.0;
    }

    return round($value / $multiple) * $multiple;
}

function formatEighths(float $cups): string
{
    $whole = (int) floor($cups);
    $frac = $cups - $whole;
    $eighths = (int) round($frac * 8);

    if ($eighths === 8) {
        $whole++;
        $eighths = 0;
    }

    if ($eighths === 0) {
        return (string) $whole;
    }

    $fracMap = [
        1 => '⅛', 2 => '¼', 3 => '⅜', 4 => '½',
        5 => '⅝', 6 => '¾', 7 => '⅞',
    ];

    $fracStr = $fracMap[$eighths] ?? number_format($frac, 3);

    return $whole > 0 ? "{$whole} {$fracStr}" : $fracStr;
}

function calculate(array $input): array
{
    $errors = [];
    $unit = ($input['weight_unit'] ?? 'lbs') === 'kg' ? 'kg' : 'lbs';
    $weightRaw = trim((string) ($input['weight'] ?? ''));
    $kcalRaw = trim((string) ($input['kcal_per_cup'] ?? ''));
    $animal = ($input['animal'] ?? '') === 'cat' ? 'cat' : 'dog';
    $lifestyle = (string) ($input['lifestyle'] ?? '');

    if ($weightRaw === '' || !is_numeric($weightRaw) || (float) $weightRaw <= 0) {
        $errors[] = 'Enter a valid weight greater than zero.';
    }

    if ($kcalRaw === '' || !is_numeric($kcalRaw) || (float) $kcalRaw <= 0) {
        $errors[] = 'Enter kcal per cup greater than zero.';
    }

    if (!isset(LIFESTYLE_FACTORS[$lifestyle])) {
        $errors[] = 'Select a lifestyle factor.';
    }

    if ($errors !== []) {
        return ['errors' => $errors];
    }

    $weightInput = (float) $weightRaw;
    $kcalPerCup = (float) $kcalRaw;
    $weightKg = $unit === 'lbs' ? $weightInput / 2.205 : $weightInput;
    $rer = 70 * ($weightKg ** 0.75);
    $factor = LIFESTYLE_FACTORS[$lifestyle][$animal];
    $mer = $factor * $rer;
    $dailyCups = $mer / $kcalPerCup;
    $twoMeals = mround($dailyCups / 2, 0.125);
    $threeMeals = mround($dailyCups / 3, 0.125);

    return [
        'errors' => [],
        'inputs' => [
            'weight' => $weightInput,
            'weight_unit' => $unit,
            'kcal_per_cup' => $kcalPerCup,
            'animal' => $animal,
            'lifestyle' => $lifestyle,
        ],
        'results' => [
            'weight_kg' => $weightKg,
            'rer' => $rer,
            'mer' => $mer,
            'factor' => $factor,
            'lifestyle_label' => LIFESTYLE_FACTORS[$lifestyle]['label'],
            'daily_cups' => $dailyCups,
            'two_meals' => $twoMeals,
            'three_meals' => $threeMeals,
        ],
    ];
}

$input = [
    'weight' => $_POST['weight'] ?? '',
    'weight_unit' => $_POST['weight_unit'] ?? 'lbs',
    'kcal_per_cup' => $_POST['kcal_per_cup'] ?? '',
    'animal' => $_POST['animal'] ?? 'dog',
    'lifestyle' => $_POST['lifestyle'] ?? 'neutered_spayed',
];

$computed = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $computed = calculate($input);
}

$hasResults = $computed !== null && ($computed['errors'] ?? []) === [];
$errors = $computed['errors'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Feeding Calculator</title>
    <style>
        :root {
            --ink: #1a2332;
            --muted: #5c6778;
            --line: #d8dee8;
            --surface: #ffffff;
            --bg: #f4f6f9;
            --accent: #2f6fed;
            --accent-soft: #e8f0ff;
            --success: #0f7b4a;
            --success-soft: #e7f6ef;
            --shadow: 0 12px 40px rgba(26, 35, 50, 0.08);
            --radius: 14px;
            --font: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: var(--font);
            color: var(--ink);
            background: linear-gradient(160deg, #eef2f8 0%, #f8fafc 45%, #eef3fb 100%);
            line-height: 1.5;
            min-height: 100vh;
        }

        .page {
            max-width: 980px;
            margin: 0 auto;
            padding: 2.5rem 1.25rem 3rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        .header h1 {
            margin: 0 0 0.35rem;
            font-size: clamp(1.6rem, 3vw, 2rem);
            letter-spacing: -0.02em;
        }

        .header p {
            margin: 0;
            color: var(--muted);
            max-width: 38rem;
        }

        .print-btn {
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--ink);
            border-radius: 999px;
            padding: 0.65rem 1.1rem;
            font: inherit;
            cursor: pointer;
            white-space: nowrap;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .print-btn:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 14px rgba(47, 111, 237, 0.12);
        }

        .layout {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 1.25rem;
            align-items: start;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.35rem 1.4rem;
        }

        .card h2 {
            margin: 0 0 1rem;
            font-size: 1.05rem;
            letter-spacing: 0.01em;
        }

        .field {
            margin-bottom: 1rem;
        }

        .field label,
        .field .legend {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 0.45rem;
        }

        .row {
            display: flex;
            gap: 0.65rem;
            align-items: center;
        }

        input[type="number"],
        input[type="text"] {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 0.72rem 0.85rem;
            font: inherit;
            color: var(--ink);
            background: #fbfcfe;
        }

        input:focus {
            outline: 2px solid rgba(47, 111, 237, 0.25);
            border-color: var(--accent);
        }

        .unit-toggle {
            display: inline-flex;
            border: 1px solid var(--line);
            border-radius: 999px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .unit-toggle label {
            margin: 0;
            text-transform: none;
            letter-spacing: 0;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--muted);
            padding: 0.55rem 0.85rem;
            cursor: pointer;
            user-select: none;
        }

        .unit-toggle input { display: none; }

        .unit-toggle input:checked + span {
            background: var(--accent);
            color: #fff;
        }

        .unit-toggle span {
            display: block;
            padding: 0.55rem 0.85rem;
        }

        .choice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.55rem;
        }

        .choice {
            position: relative;
        }

        .choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .choice span {
            display: block;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 0.72rem 0.85rem;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            background: #fbfcfe;
            transition: border-color 0.15s, background 0.15s;
        }

        .choice input:checked + span {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: #1849a9;
        }

        .lifestyle-list {
            display: grid;
            gap: 0.45rem;
        }

        .lifestyle-list label {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 0.62rem 0.75rem;
            cursor: pointer;
            background: #fbfcfe;
            text-transform: none;
            letter-spacing: 0;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--ink);
        }

        .lifestyle-list input { accent-color: var(--accent); }

        .submit {
            width: 100%;
            margin-top: 0.35rem;
            border: none;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            font: inherit;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #2f6fed, #2457c5);
            cursor: pointer;
        }

        .submit:hover { filter: brightness(1.03); }

        .errors {
            margin: 0 0 1rem;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            background: #fff1f0;
            border: 1px solid #f3c1bd;
            color: #9b1c1c;
        }

        .errors ul {
            margin: 0;
            padding-left: 1.1rem;
        }

        .results-empty {
            color: var(--muted);
            font-size: 0.95rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .metric {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 0.85rem 0.95rem;
            background: #fbfcfe;
        }

        .metric .label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--muted);
            margin-bottom: 0.25rem;
        }

        .metric .value {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .metric .sub {
            font-size: 0.82rem;
            color: var(--muted);
            margin-top: 0.15rem;
        }

        .highlight-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .highlight {
            border-radius: 12px;
            padding: 1rem;
            background: var(--success-soft);
            border: 1px solid #b9e3ce;
        }

        .highlight .label {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #256f4a;
            margin-bottom: 0.35rem;
        }

        .highlight .value {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--success);
            line-height: 1.15;
        }

        .highlight .detail {
            font-size: 0.84rem;
            color: #3d6f57;
            margin-top: 0.25rem;
        }

        .footnote {
            margin-top: 1rem;
            font-size: 0.82rem;
            color: var(--muted);
        }

        .print-meta {
            display: none;
        }

        @media (max-width: 860px) {
            .layout { grid-template-columns: 1fr; }
            .highlight-row { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: 1fr; }
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                max-width: none;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .print-meta {
                display: block;
                margin-bottom: 1rem;
                padding-bottom: 0.75rem;
                border-bottom: 2px solid #000;
            }

            .print-meta h1 {
                margin: 0 0 0.25rem;
                font-size: 1.35rem;
            }

            .print-meta p {
                margin: 0;
                font-size: 0.85rem;
                color: #333;
            }

            .card {
                box-shadow: none;
                border-color: #ccc;
                break-inside: avoid;
            }

            .layout {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }

            .highlight,
            .metric {
                background: #fff;
            }

            .highlight .value {
                color: #000;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="print-meta">
        <h1>Pet Feeding Calculator — Results</h1>
        <p>Generated <?= htmlspecialchars(date('F j, Y \a\t g:i A'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="header">
        <div>
            <h1>Pet Feeding Calculator</h1>
            <p>Estimate daily caloric needs (RER/MER) and cup portions from body weight, lifestyle, and food energy density.</p>
        </div>
        <?php if ($hasResults): ?>
            <button type="button" class="print-btn no-print" onclick="window.print()">Print results</button>
        <?php endif; ?>
    </div>

    <div class="layout">
        <section class="card no-print">
            <h2>Patient &amp; food inputs</h2>

            <?php if ($errors !== []): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="field">
                    <label for="weight">Body weight</label>
                    <div class="row">
                        <input
                            type="number"
                            id="weight"
                            name="weight"
                            step="0.1"
                            min="0"
                            required
                            value="<?= htmlspecialchars((string) $input['weight'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Enter weight"
                        >
                        <div class="unit-toggle">
                            <label>
                                <input type="radio" name="weight_unit" value="lbs" <?= $input['weight_unit'] === 'lbs' ? 'checked' : '' ?>>
                                <span>LBS</span>
                            </label>
                            <label>
                                <input type="radio" name="weight_unit" value="kg" <?= $input['weight_unit'] === 'kg' ? 'checked' : '' ?>>
                                <span>KG</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label for="kcal_per_cup">Food energy density (kcal/cup)</label>
                    <input
                        type="number"
                        id="kcal_per_cup"
                        name="kcal_per_cup"
                        step="1"
                        min="1"
                        required
                        value="<?= htmlspecialchars((string) $input['kcal_per_cup'], ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="e.g. 350"
                    >
                </div>

                <div class="field">
                    <span class="legend">Animal</span>
                    <div class="choice-grid">
                        <label class="choice">
                            <input type="radio" name="animal" value="dog" <?= $input['animal'] === 'dog' ? 'checked' : '' ?>>
                            <span>Dog</span>
                        </label>
                        <label class="choice">
                            <input type="radio" name="animal" value="cat" <?= $input['animal'] === 'cat' ? 'checked' : '' ?>>
                            <span>Cat</span>
                        </label>
                    </div>
                </div>

                <div class="field">
                    <span class="legend">Lifestyle factor</span>
                    <div class="lifestyle-list">
                        <?php foreach (LIFESTYLE_FACTORS as $key => $row): ?>
                            <label>
                                <input
                                    type="radio"
                                    name="lifestyle"
                                    value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $input['lifestyle'] === $key ? 'checked' : '' ?>
                                >
                                <?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="submit">Calculate feeding plan</button>
            </form>
        </section>

        <section class="card">
            <h2><?= $hasResults ? 'Feeding plan' : 'Results' ?></h2>

            <?php if (!$hasResults): ?>
                <p class="results-empty">Enter patient details and select Calculate to see RER, MER, and daily cup amounts.</p>
            <?php else:
                $r = $computed['results'];
                $i = $computed['inputs'];
                ?>
                <div class="summary-grid">
                    <div class="metric">
                        <div class="label">Weight (kg)</div>
                        <div class="value"><?= number_format($r['weight_kg'], 2) ?> kg</div>
                        <div class="sub">
                            <?= number_format($i['weight'], 1) ?>
                            <?= $i['weight_unit'] === 'lbs' ? 'lb' : 'kg' ?>
                            input
                        </div>
                    </div>
                    <div class="metric">
                        <div class="label">Lifestyle factor</div>
                        <div class="value"><?= number_format($r['factor'], 1) ?>×</div>
                        <div class="sub"><?= htmlspecialchars($r['lifestyle_label'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="metric">
                        <div class="label">RER</div>
                        <div class="value"><?= number_format($r['rer'], 0) ?> kcal</div>
                        <div class="sub">70 × weight<sup>0.75</sup></div>
                    </div>
                    <div class="metric">
                        <div class="label">MER</div>
                        <div class="value"><?= number_format($r['mer'], 0) ?> kcal</div>
                        <div class="sub">Factor × RER</div>
                    </div>
                </div>

                <div class="highlight-row">
                    <div class="highlight">
                        <div class="label">Total daily amount</div>
                        <div class="value"><?= number_format($r['daily_cups'], 2) ?></div>
                        <div class="detail">cups per day</div>
                    </div>
                    <div class="highlight">
                        <div class="label">2 meals per day</div>
                        <div class="value"><?= htmlspecialchars(formatEighths($r['two_meals']), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="detail">cups per meal (⅛ cup precision)</div>
                    </div>
                    <div class="highlight">
                        <div class="label">3 meals per day</div>
                        <div class="value"><?= htmlspecialchars(formatEighths($r['three_meals']), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="detail">cups per meal (⅛ cup precision)</div>
                    </div>
                </div>

                <p class="footnote">
                    <?= ucfirst(htmlspecialchars($i['animal'], ENT_QUOTES, 'UTF-8')) ?> ·
                    <?= htmlspecialchars($r['lifestyle_label'], ENT_QUOTES, 'UTF-8') ?> ·
                    <?= number_format($i['kcal_per_cup'], 0) ?> kcal/cup
                </p>
            <?php endif; ?>
        </section>
    </div>
</div>
</body>
</html>
