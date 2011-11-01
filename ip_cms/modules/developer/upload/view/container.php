<style>
.ipUploadButtons {
    position: absolute; z-index: 100;
}

.ipUploadWindow{
    overflow: hidden;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -o-user-select: none;
    user-select: none;
}

.ipUploadBrowseButton{
    cursor: pointer;
}
</style>

<div class="ipUploadWindow ui-widget-content">
    <div class="ipUploadButtons">
        <div class="ipUploadBrowseContainer" id="ipUploadContainer_' + uniqueId + '">
            <div class="ipUploadBrowseButton" id="ipUploadButton_' + uniqueId + '">Upload new</div>
        </div>
        <div class="ipUploadLargerButton">Larger</div>
        <div class="ipUploadSmallerButton">Smaller</div>
    </div>
    <div class="ipUploadDragContainer">
        <img class="ipUploadImage" src="" alt="picture" />
    </div>
</div>