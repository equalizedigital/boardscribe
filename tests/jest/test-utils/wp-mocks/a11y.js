/**
 * Test-only stand-in for @wordpress/a11y (not an installed package here -
 * like @wordpress/components, this codebase only ever consumes it as a
 * DependencyExtractionWebpackPlugin-externalized wp.a11y global, see the
 * components mock's own docblock). No test currently asserts against
 * announcements, so this is a plain no-op rather than a jest.fn() - keeps
 * this file loadable from contexts (e.g. non-*.test.js modules) where the
 * `jest` global isn't in scope.
 */
export function speak() {}
