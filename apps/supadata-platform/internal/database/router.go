package database

import (
	"context"
	"database/sql"
	"errors"
	"regexp"
	"sync"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

var ErrProjectDatabaseNotConfigured = errors.New("project database is not configured")

var databaseProjectIDPattern = regexp.MustCompile(`^[a-z0-9]+(?:-[a-z0-9]+)*$`)

type Resolver interface {
	Resolve(context.Context, project.Project) (*sql.DB, error)
}

type Router struct {
	defaultDB *sql.DB
	mu        sync.RWMutex
	byProject map[string]*sql.DB
}

func NewRouter(defaultDB *sql.DB) *Router {
	return &Router{defaultDB: defaultDB, byProject: make(map[string]*sql.DB)}
}

func (r *Router) Register(projectID string, database *sql.DB) error {
	if !databaseProjectIDPattern.MatchString(projectID) {
		return errors.New("invalid project ID")
	}
	if database == nil {
		return errors.New("project database is required")
	}
	r.mu.Lock()
	defer r.mu.Unlock()
	if existing, ok := r.byProject[projectID]; ok && existing != database {
		return errors.New("project database is already registered")
	}
	r.byProject[projectID] = database
	return nil
}

func (r *Router) Resolve(_ context.Context, value project.Project) (*sql.DB, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()
	if database, ok := r.byProject[value.ID]; ok {
		return database, nil
	}
	return nil, ErrProjectDatabaseNotConfigured
}

type databaseContextKey struct{}

func WithConnection(ctx context.Context, database *sql.DB) context.Context {
	return context.WithValue(ctx, databaseContextKey{}, database)
}

func ConnectionFromContext(ctx context.Context) (*sql.DB, bool) {
	database, ok := ctx.Value(databaseContextKey{}).(*sql.DB)
	return database, ok && database != nil
}
