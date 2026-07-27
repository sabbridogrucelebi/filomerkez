import React from 'react';
import { View, Text } from 'react-native';

const MapView = React.forwardRef((props, ref) => {
    React.useImperativeHandle(ref, () => ({
        animateToRegion: () => {},
        animateCamera: () => {}
    }));
    return (
        <View style={[{ backgroundColor: '#242f3e', justifyContent: 'center', alignItems: 'center' }, props.style]}>
            <Text style={{ color: '#fff', fontSize: 16 }}>Harita (Web'de Gizli)</Text>
        </View>
    );
});

const MarkerBase = React.forwardRef((props, ref) => <View ref={ref}>{props.children}</View>);
const MarkerAnimated = React.forwardRef((props, ref) => {
    React.useImperativeHandle(ref, () => ({
        animateMarkerToCoordinate: () => {}
    }));
    return <View>{props.children}</View>;
});

const Marker = Object.assign(MarkerBase, { Animated: MarkerAnimated });
const Circle = (props) => null;
const PROVIDER_GOOGLE = null;

class AnimatedRegion {
    constructor(obj) { Object.assign(this, obj); }
    timing() { return { start: () => {} }; }
    setValue() {}
}

export default MapView;
export { Marker, Circle, AnimatedRegion, PROVIDER_GOOGLE };
