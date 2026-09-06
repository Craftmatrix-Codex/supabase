package database

import (
	"context"
	"database/sql"
	"errors"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

var ErrSharedDatabaseMissing = errors.New("shared database is required")

type SharedProvisioner struct {
	DB     *sql.DB
	Router *Router
}

func (p SharedProvisioner) ProvisionDatabase(ctx context.Context, value project.Project) error {
	if p.DB == nil {
		return ErrSharedDatabaseMissing
	}
	if err := p.DB.PingContext(ctx); err != nil {
		return err
	}
	if p.Router != nil {
		if err := p.Router.Register(value.ID, p.DB); err != nil {
			return err
		}
	}
	return nil
}
