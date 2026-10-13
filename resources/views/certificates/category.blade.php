{{--
    Rendered by DomPDF, not a browser: no Tailwind, no flexbox, no grid.
    Tables and block elements with inline-ish CSS only, and DejaVu Sans because
    it is the font DomPDF actually ships.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $category }} — Certificate of Completion</title>
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            color: #0E1529;
            background: #ffffff;
        }

        /*
         * No fixed heights. DomPDF's usable height on A4 landscape is well
         * under the nominal 595pt once nested boxes are counted, and a frame
         * one point too tall spills onto a second page. Letting the frame wrap
         * its content keeps the certificate to ONE page whatever the name
         * length, and the page padding supplies the margin.
         */
        .sheet {
            width: 100%;
            padding: 34pt 28pt;
        }

        .frame {
            border: 2pt solid #0E1529;
            padding: 6pt;
        }

        .inner {
            border: 0.8pt solid #E8B339;
            padding: 26pt 40pt 22pt;
            text-align: center;
        }

        .eyebrow {
            font-size: 8pt;
            letter-spacing: 3pt;
            color: #6B7A9B;
            text-transform: uppercase;
            margin: 0 0 4pt;
        }

        .brand {
            font-size: 13pt;
            font-weight: bold;
            color: #0E1529;
            margin: 0;
        }

        .rule {
            width: 56pt;
            height: 2pt;
            background: #E8B339;
            margin: 14pt auto;
        }

        .title {
            font-size: 20pt;
            letter-spacing: 4pt;
            text-transform: uppercase;
            margin: 0 0 18pt;
            color: #0E1529;
        }

        .lead { font-size: 10pt; color: #6B7A9B; margin: 0 0 6pt; }

        .name {
            font-size: 32pt;
            font-weight: bold;
            margin: 0 0 6pt;
            color: #0E1529;
        }

        .name-underline {
            width: 320pt;
            height: 0.8pt;
            background: #C9D2E6;
            margin: 0 auto 16pt;
        }

        .body-copy { font-size: 11pt; line-height: 1.7; margin: 0; color: #2A3550; }
        .category { font-size: 15pt; font-weight: bold; color: #0E1529; }

        .meta { width: 100%; margin-top: 30pt; border-collapse: collapse; }
        .meta td { font-size: 8.5pt; color: #6B7A9B; padding-top: 7pt; }
        .meta .value { font-size: 10pt; color: #0E1529; font-weight: bold; }
        .meta .left { text-align: left; width: 40%; }
        .meta .mid { text-align: center; width: 20%; }
        .meta .right { text-align: right; width: 40%; }

        .verify {
            margin-top: 12pt;
            font-size: 7.5pt;
            color: #6B7A9B;
        }

        .disclaimer {
            margin-top: 8pt;
            font-size: 7pt;
            line-height: 1.5;
            color: #8795B8;
        }

        /* Three candles, drawn as plain boxes so DomPDF cannot get them wrong. */
        .marks { margin: 0 auto 10pt; height: 20pt; }
        .mark { display: inline-block; width: 4pt; margin: 0 2pt; vertical-align: bottom; }
        .mark-sm { height: 10pt; background: #8795B8; }
        .mark-lg { height: 20pt; background: #0FBF7F; }
        .mark-md { height: 14pt; background: #8795B8; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="frame">
        <div class="inner">

            <div class="marks">
                <span class="mark mark-sm"></span>
                <span class="mark mark-lg"></span>
                <span class="mark mark-md"></span>
            </div>

            <p class="eyebrow">NSE &middot; BSE &middot; Derivatives</p>
            <p class="brand">{{ $brand }}</p>

            <div class="rule"></div>

            <p class="title">Certificate of Completion</p>

            <p class="lead">This certifies that</p>
            <p class="name">{{ $name }}</p>
            <div class="name-underline"></div>

            <p class="body-copy">
                has watched all <strong>{{ $lessons }}</strong>
                {{ \Illuminate\Support\Str::plural('lesson', $lessons) }} in<br>
                <span class="category">{{ $category }}</span>
            </p>

            <table class="meta">
                <tr>
                    <td class="left">Completed on</td>
                    <td class="mid">Certificate reference</td>
                    <td class="right">Issued by</td>
                </tr>
                <tr>
                    <td class="left value">{{ $completedAt->format('d F Y') }}</td>
                    <td class="mid value">{{ $reference }}</td>
                    <td class="right value">{{ $brand }}</td>
                </tr>
            </table>

            <p class="verify">Verify at {{ $verifyUrl }}</p>

            <p class="disclaimer">
                This certificate records course completion only. It is not a licence, a qualification,
                or financial advice. {{ $brand }} does not give buy or sell calls, and trading in
                securities carries risk of loss.
            </p>
        </div>
    </div>
</div>
</body>
</html>
