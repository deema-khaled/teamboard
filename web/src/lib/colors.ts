// Full class names (not built from strings) so Tailwind keeps them in the build.

export const boardGradients: Record<string, string> = {
    indigo: 'from-indigo-500 to-violet-500',
    sky: 'from-sky-500 to-cyan-500',
    emerald: 'from-emerald-500 to-teal-500',
    amber: 'from-amber-400 to-orange-500',
    rose: 'from-rose-500 to-pink-500',
    violet: 'from-violet-500 to-fuchsia-500',
    slate: 'from-slate-600 to-slate-800',
};

export const labelClasses: Record<string, string> = {
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    yellow: 'bg-yellow-400',
    green: 'bg-emerald-500',
    blue: 'bg-sky-500',
    purple: 'bg-violet-500',
};

export const boardColors = Object.keys(boardGradients);
export const labels = Object.keys(labelClasses);
