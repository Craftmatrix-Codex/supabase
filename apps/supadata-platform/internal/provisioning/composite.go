package provisioning

import (
	"context"
	"errors"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

var (
	ErrDatabaseProvisionerMissing = errors.New("database provisioner is required")
	ErrStorageProvisionerMissing  = errors.New("storage provisioner is required")
)

type DatabaseProvisioner interface {
	ProvisionDatabase(context.Context, project.Project) error
}

type StorageProvisioner interface {
	EnsureBucket(context.Context, string) error
}

type Composite struct {
	Database DatabaseProvisioner
	Storage  StorageProvisioner
}

func (c Composite) ProvisionProject(ctx context.Context, value project.Project) error {
	if c.Database == nil {
		return ErrDatabaseProvisionerMissing
	}
	if c.Storage == nil {
		return ErrStorageProvisionerMissing
	}
	if err := c.Database.ProvisionDatabase(ctx, value); err != nil {
		return err
	}
	return c.Storage.EnsureBucket(ctx, value.Scope.Storage.Bucket)
}
