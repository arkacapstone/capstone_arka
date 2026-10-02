/** `themed` lightens the navy parts in dark mode; leave it off on always-light surfaces like the login card. */
export default function ArkaLogo({ className = '', themed = false, ...props }) {
    const navy = themed ? 'fill-[#10203f] dark:fill-console-heading' : 'fill-[#10203f]';

    return (
        <svg
            {...props}
            viewBox="0 0 120 80"
            xmlns="http://www.w3.org/2000/svg"
            className={className}
            role="img"
            aria-label="ARKA"
        >
            {/* Sail shaped like an "A" */}
            <path d="M34 44 L54 6 L60 18 L44 44 Z" className={navy} />
            <path d="M56 10 L74 44 L63 44 L52 22 Z" fill="#f3a620" />
            <path d="M40 38 Q58 30 76 36 L78 40 Q58 35 38 42 Z" fill="#2bb3c7" />

            {/* Waves */}
            <path
                d="M22 50 Q34 44 46 50 T70 50 T94 50"
                fill="none"
                stroke="#1a7a8c"
                strokeWidth="3.5"
                strokeLinecap="round"
            />
            <path
                d="M30 56 Q40 51 50 56 T70 56 T88 56"
                fill="none"
                stroke="#2bb3c7"
                strokeWidth="2.5"
                strokeLinecap="round"
            />

            {/* Wordmark */}
            <text
                x="60"
                y="76"
                textAnchor="middle"
                fontFamily="Figtree, sans-serif"
                fontSize="15"
                fontWeight="700"
                letterSpacing="3"
                className={navy}
            >
                ARKA
            </text>
        </svg>
    );
}
