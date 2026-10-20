/**
 * The sea behind the login screens: a pale sun and layered navy, teal and sand waves.
 * `low` keeps the waves near the bottom so the text above them stays readable.
 */
export default function WaveBackdrop({ low = false }) {
    const heights = low ? ['h-[38%]', 'h-[30%]', 'h-[18%]'] : ['h-[62%]', 'h-[50%]', 'h-[30%]'];

    return (
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
            {/* Pale sun in the top right */}
            <div className="absolute -right-40 -top-56 h-[46rem] w-[46rem] rounded-full bg-arka-sky sm:-right-24" />

            {/* Layered waves: navy, teal, sand */}
            <svg
                className={`absolute bottom-0 left-[-5%] ${heights[0]} w-[110%]`}
                viewBox="0 0 1440 560"
                preserveAspectRatio="none"
            >
                <path
                    d="M0 330 C 220 150, 470 90, 720 150 C 930 200, 1130 320, 1440 250 L1440 560 L0 560 Z"
                    fill="#10203f"
                />
            </svg>
            <svg
                className={`absolute bottom-0 left-[-5%] ${heights[1]} w-[110%]`}
                viewBox="0 0 1440 450"
                preserveAspectRatio="none"
            >
                <path
                    d="M0 260 C 260 110, 520 70, 760 120 C 1000 170, 1200 260, 1440 190 L1440 450 L0 450 Z"
                    fill="#1a7a8c"
                />
            </svg>
            <svg
                className={`absolute bottom-0 left-[-5%] ${heights[2]} w-[110%]`}
                viewBox="0 0 1440 270"
                preserveAspectRatio="none"
            >
                <path
                    d="M0 230 C 200 170, 330 150, 520 150 C 820 150, 1100 60, 1440 90 L1440 270 L0 270 Z"
                    fill="#f3a620"
                />
            </svg>
        </div>
    );
}

export function MailIcon(props) {
    return (
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.6" {...props}>
            <rect x="2.5" y="4.5" width="15" height="11" rx="2" />
            <path d="m3 6 7 5 7-5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>

    );
}
