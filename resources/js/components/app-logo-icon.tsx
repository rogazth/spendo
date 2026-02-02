import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            {/* Wallet base */}
            <rect
                x="2"
                y="10"
                width="36"
                height="26"
                rx="4"
                fill="currentColor"
                opacity="0.9"
            />
            {/* Wallet flap */}
            <path
                d="M6 10V8C6 5.79086 7.79086 4 10 4H30C32.2091 4 34 5.79086 34 8V10"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
            {/* Coin slot / card holder accent */}
            <rect
                x="24"
                y="18"
                width="10"
                height="10"
                rx="2"
                fill="currentColor"
                opacity="0.3"
            />
            {/* Dollar sign / currency symbol */}
            <text
                x="12"
                y="28"
                fontSize="14"
                fontWeight="bold"
                fill="currentColor"
                opacity="0.3"
            >
                $
            </text>
        </svg>
    );
}
