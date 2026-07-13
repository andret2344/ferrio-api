import {Chart, ChartConfiguration, ChartDataset, registerables} from 'chart.js';

Chart.register(...registerables);

// Categorical slots, dark steps, kept in this fixed order — the order is what keeps adjacent series
// separable for colour-vision deficiencies, so series take slots by position and hues are never
// cycled or recoloured when a series disappears. Every chart on these pages also ships a legend and
// the same numbers in a table underneath, which is the secondary encoding the palette requires.
const PALETTE = ['#3987e5', '#199e70', '#c98500', '#008300', '#9085e9', '#e66767', '#d55181', '#d95926'];

const SURFACE = '#0c0c24';
const GRID = '#1a1a3a';
const TICK = '#7a7a9a';
const LABEL = '#c8c8e0';

type ChartKind = 'line' | 'bar' | 'stacked_bar' | 'horizontal_bar' | 'doughnut';

interface ChartSeries {
    readonly label: string;
    readonly data: readonly number[];
}

interface ChartSpec {
    readonly kind: ChartKind;
    // A label may be an array of lines — Chart.js stacks them on the tick.
    readonly labels: ReadonlyArray<string | string[]>;
    readonly series: readonly ChartSeries[];
    // Optional per-category detail shown only on hover, so the axis stays short (the top-reporters
    // chart puts the reporter's e-mail here).
    readonly notes?: readonly string[];
}

function color(index: number): string {
    return PALETTE[index % PALETTE.length];
}

function labels(spec: ChartSpec): unknown[] {
    return spec.labels.map(label => (Array.isArray(label) ? [...label] : label));
}

function lineDatasets(spec: ChartSpec): ChartDataset<'line'>[] {
    return spec.series.map((series, index) => ({
        label: series.label,
        data: [...series.data],
        borderColor: color(index),
        backgroundColor: color(index),
        borderWidth: 2,
        pointRadius: spec.labels.length > 40 ? 0 : 3,
        pointHoverRadius: 5,
        tension: 0.25,
    }));
}

// A 2px ring in the surface colour is the gap that keeps touching marks — stacked segments, adjacent
// bars, doughnut arcs — from bleeding into one another.
function barDatasets(spec: ChartSpec): ChartDataset<'bar'>[] {
    return spec.series.map((series, index) => ({
        label: series.label,
        data: [...series.data],
        backgroundColor: color(index),
        borderColor: SURFACE,
        borderWidth: 2,
        borderRadius: 4,
        borderSkipped: false,
    }));
}

function doughnutDatasets(spec: ChartSpec): ChartDataset<'doughnut'>[] {
    return spec.series.map(series => ({
        label: series.label,
        data: [...series.data],
        backgroundColor: series.data.map((_, index) => color(index)),
        borderColor: SURFACE,
        borderWidth: 2,
    }));
}

function scales(spec: ChartSpec): Record<string, unknown> {
    const horizontal = spec.kind === 'horizontal_bar';
    const stacked = spec.kind === 'stacked_bar';
    const category = {
        stacked: stacked,
        grid: {display: false},
        border: {color: GRID},
        ticks: {color: TICK, autoSkip: !horizontal, maxRotation: 0},
    };
    const value = {
        stacked: stacked,
        beginAtZero: true,
        grid: {color: GRID},
        border: {display: false},
        ticks: {color: TICK, precision: 0},
    };
    if (horizontal) {
        return {x: value, y: category};
    }
    return {x: category, y: value};
}

function build(spec: ChartSpec): ChartConfiguration {
    const isDoughnut = spec.kind === 'doughnut';
    const options: Record<string, unknown> = {
        responsive: true,
        maintainAspectRatio: false,
        // "index" mode looks the hovered position up along the category axis, which for a horizontal
        // bar is y — leaving it at the default x means the pointer never matches a bar and no tooltip
        // ever shows.
        interaction: {
            mode: isDoughnut ? 'nearest' : 'index',
            intersect: false,
            axis: spec.kind === 'horizontal_bar' ? 'y' : 'x',
        },
        plugins: {
            // A single series is named by the chart's own title, so a one-entry legend is noise.
            legend: {
                display: isDoughnut || spec.series.length > 1,
                position: isDoughnut ? 'right' : 'top',
                labels: {color: LABEL, boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: 'circle'},
            },
            tooltip: {
                backgroundColor: '#12122e',
                borderColor: '#2a2a4a',
                borderWidth: 1,
                titleColor: '#e6e6f6',
                bodyColor: LABEL,
                padding: 10,
                displayColors: true,
                usePointStyle: true,
                callbacks: {
                    afterTitle: (items: {dataIndex: number}[]) => spec.notes?.[items[0]?.dataIndex] ?? '',
                },
            },
        },
    };
    if (isDoughnut) {
        return {
            type: 'doughnut',
            data: {labels: labels(spec), datasets: doughnutDatasets(spec)},
            options: {...options, cutout: '62%'},
        } as ChartConfiguration;
    }
    if (spec.kind === 'line') {
        return {
            type: 'line',
            data: {labels: labels(spec), datasets: lineDatasets(spec)},
            options: {...options, scales: scales(spec)},
        } as ChartConfiguration;
    }
    return {
        type: 'bar',
        data: {labels: labels(spec), datasets: barDatasets(spec)},
        options: {...options, indexAxis: spec.kind === 'horizontal_bar' ? 'y' : 'x', scales: scales(spec)},
    } as ChartConfiguration;
}

function readSpec(canvas: HTMLCanvasElement): ChartSpec | null {
    const sourceId = canvas.dataset.chart;
    if (!sourceId) {
        return null;
    }
    const source = document.getElementById(sourceId);
    if (!source?.textContent) {
        return null;
    }
    try {
        return JSON.parse(source.textContent) as ChartSpec;
    } catch {
        return null;
    }
}

function initCharts(): void {
    document.querySelectorAll<HTMLCanvasElement>('canvas[data-chart]').forEach(canvas => {
        const spec = readSpec(canvas);
        if (!spec || spec.labels.length === 0) {
            return;
        }
        new Chart(canvas, build(spec));
    });
}

document.addEventListener('DOMContentLoaded', initCharts);
