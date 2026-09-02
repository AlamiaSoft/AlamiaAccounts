# Frontend Coding Standards & Best Practices

---

## 1. TypeScript & Component Conventions
- **Strict Typing**: Avoid `any` where possible; define dedicated interfaces for payloads in `types/` or `lib/api/`.
- **Component Isolation**: Use `'use client';` on interactive components. Keep state logic encapsulated in custom hooks.
- **Form Validation**: Always validate debits equal credits before triggering voucher mutations.

---

## 2. TanStack React Query Patterns
- Centralize all query keys in hooks.
- Always perform query invalidation on mutation success (e.g., `queryClient.invalidateQueries({ queryKey: ['vouchers'] })`).
- Handle loading and error states using Shadcn skeletons/spinners and toast notifications.

---

## 3. Styling & Accessibility
- Use Tailwind CSS utility classes exclusively.
- Use Lucide icons consistently (`lucide-react`).
- Maintain dark/light mode compatibility using semantic Tailwind color tokens (`bg-background`, `text-foreground`, `border-input`).
