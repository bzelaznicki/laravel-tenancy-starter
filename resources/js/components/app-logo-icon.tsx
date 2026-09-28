import type { SVGAttributes } from 'react';

/** The app mark: a plain rounded square, drawn in the current colour. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 22 22"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect width="22" height="22" rx="5" />
        </svg>
    );
}
