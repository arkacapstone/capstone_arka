import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

/** Text input for the auth pages. `invalid` outlines it in red to match its error message. */
export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, invalid = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            aria-invalid={invalid || undefined}
            className={
                'rounded-md shadow-sm ' +
                (invalid
                    ? 'border-red-600 focus:border-red-600 focus:ring-red-600 '
                    : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 ') +
                className
            }
            ref={localRef}
        />
    );
});
