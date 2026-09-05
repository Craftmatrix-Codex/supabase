package provisioning

import (
	"context"
	"errors"
	"reflect"
	"testing"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

type recordingDatabaseProvisioner struct {
	events *[]string
	err    error
}

func (p recordingDatabaseProvisioner) ProvisionDatabase(_ context.Context, scope project.DatabaseScope) error {
	*p.events = append(*p.events, "database:"+scope.Name)
	return p.err
}

type recordingBucketProvisioner struct {
	events *[]string
	err    error
}

func (p recordingBucketProvisioner) EnsureBucket(_ context.Context, bucket string) error {
	*p.events = append(*p.events, "bucket:"+bucket)
	return p.err
}

func TestCompositeProvisionsDatabaseBeforeBucket(t *testing.T) {
	events := []string{}
	composite := Composite{
		Database: recordingDatabaseProvisioner{events: &events},
		Storage:  recordingBucketProvisioner{events: &events},
	}
	value := project.Project{ID: "alpha", Scope: project.ResourceScope{
		Database: project.DatabaseScope{Name: "supadata_alpha"},
		Storage:  project.StorageScope{Bucket: "supadata-alpha"},
	}}
	if err := composite.ProvisionProject(context.Background(), value); err != nil {
		t.Fatalf("ProvisionProject() error = %v", err)
	}
	want := []string{"database:supadata_alpha", "bucket:supadata-alpha"}
	if !reflect.DeepEqual(events, want) {
		t.Fatalf("events = %v, want %v", events, want)
	}
}

func TestCompositeStopsBeforeBucketWhenDatabaseFails(t *testing.T) {
	events := []string{}
	composite := Composite{
		Database: recordingDatabaseProvisioner{events: &events, err: errors.New("database unavailable")},
		Storage:  recordingBucketProvisioner{events: &events},
	}
	value := project.Project{ID: "alpha", Scope: project.ResourceScope{
		Database: project.DatabaseScope{Name: "supadata_alpha"},
		Storage:  project.StorageScope{Bucket: "supadata-alpha"},
	}}
	if err := composite.ProvisionProject(context.Background(), value); err == nil {
		t.Fatal("ProvisionProject() unexpectedly succeeded")
	}
	want := []string{"database:supadata_alpha"}
	if !reflect.DeepEqual(events, want) {
		t.Fatalf("events = %v, want %v", events, want)
	}
}
