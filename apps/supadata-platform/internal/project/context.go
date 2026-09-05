package project

import (
	"context"
	"errors"
)

var ErrNotFound = errors.New("project not found")

type scopeContextKey struct{}

func WithScope(ctx context.Context, value Project) context.Context {
	return context.WithValue(ctx, scopeContextKey{}, value)
}

func ScopeFromContext(ctx context.Context) (Project, bool) {
	value, ok := ctx.Value(scopeContextKey{}).(Project)
	return value, ok
}
